<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;

class WikiBasedComposer extends ConfluenceComposerBase {

	/**
	 * Round-robin index into the flattened list of (wiki, namespace) pairs across *all*
	 * wikis, shared across every call to storeMigrationResult() in this instance. This is
	 * what gives worker sharding namespace-level granularity instead of only wiki-level
	 * granularity: a single large wiki with many namespaces still gets spread across every
	 * worker, not pinned to just one of them.
	 *
	 * @var int
	 */
	private int $namespaceShardIndex = 0;

	/** @var PipeChannel|null Lazily opened; only used when isWorker() actually sends data. */
	private ?PipeChannel $pipeChannel = null;

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function doBuildXML( Builder $builder ): void {
		$wikiNames = $this->dataLookup->getWikisConfigWikiNames();
		if ( $wikiNames === [] ) {
			// This composer is only for wiki-based deployments.
			// If no wikis are configured, we will not process anything.
			return;
		}

		if ( $this->isFinalizeOnly() ) {
			// Workers only produced per-namespace artifacts (self-contained). Aggregate the
			// once-per-wiki artifacts here, in a single non-parallel pass, without redoing
			// any of the actual (expensive) content processing.
			$this->finalizeWikis( $wikiNames, $builder );
			$this->writeUserReadableDBLog( $this->dbLog );
			return;
		}

		// Run space dependent processors for each space
		// If wikis are configured, we will process spaces grouped by wiki name
		$this->output->writeln( "Data is assigned to some wikis." );

		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->dataLookup->getWikisConfigSpacesForWikiName( $wikiName );
			if ( $spaces === [] ) {
				$this->output->writeln( "No spaces found for wiki '$wikiName'." );
				continue;
			}

			// Shared content and the wiki-level sidebar/deployment.txt/wikiimport.sh are
			// once-per-wiki artifacts. If multiple workers process namespaces of the *same*
			// wiki concurrently, only one of them (the single, non-parallel run) may write
			// these; otherwise workers would race on the same files or overwrite each other's
			// (partial) ComposerDeploymentInfo. They are produced later by the finalize pass
			// instead — see isFinalizeOnly() above.
			if ( !$this->isWorker() ) {
				$this->runSharedContentProcessors(
					$builder,
					$wikiName . '/_shared',
					array_map( 'intval', array_column( $spaces, 'space_id' ) )
				);
			}

			$spacesMap = $this->buildSpacesMap( $spaces );
			$deploymentInfo = $this->storeMigrationResult( $spacesMap, $builder, $wikiName );

			if ( !$this->isWorker() ) {
				$sidebarProcessor = new Sidebar(
					$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
				);
				$sidebarProcessor->setSubDir( $wikiName );
				$sidebarProcessor->execute();

				$this->addWikiImportHelper( $wikiName );
				$this->writeDeploymentLog( $deploymentInfo, $wikiName );
			}

			$this->output->writeln( "Processing wiki '$wikiName' with " . count( $spaces ) . " spaces." );
		}

		if ( !$this->isWorker() ) {
			$this->writeUserReadableDBLog( $this->dbLog );
		}
	}

	/**
	 * Run the per-namespace processors for this worker's assigned slice of $spacesMap.
	 * Namespaces are round-robin sliced across worker processes (namespace-level, not
	 * wiki-level, granularity); namespace size is not taken into account.
	 *
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @param string $wikiName
	 * @return ComposerDeploymentInfo Aggregated over the namespaces *this call* processed
	 *   (all of them for a single-process run, only this worker's slice otherwise).
	 */
	private function storeMigrationResult(
		array $spacesMap, Builder $builder, string $wikiName = ''
	): ComposerDeploymentInfo {
		$wikiDeploymentInfo = new ComposerDeploymentInfo();

		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip namespace '$namespace' by configuration." );
				continue;
			}

			if ( !$this->isMyShare( $this->namespaceShardIndex++ ) ) {
				continue;
			}

			if ( $wikiName === '' ) {
				$this->output->writeln( "Wikiname for namespace '$namespace' is empty. -> skipping" );
				continue;
			}
			$subDir = $wikiName . '/' . $namespace;

			// Own instance per namespace: keeps this namespace's processing (and its
			// skipped-pages log) self-contained and safe to run concurrently with other
			// workers processing other namespaces of the same wiki.
			$namespaceDeploymentInfo = new ComposerDeploymentInfo();
			$namespaceDeploymentInfo->addNamespace( $namespace );

			$processors = $this->initProcessorsForSpaceContent( $builder, $namespaceDeploymentInfo );

			$spaceIds = array_keys( $spaces );
			foreach ( $processors as $processor ) {
				if ( $processor instanceof ISubDirAware ) {
					$processor->setSubDir( $subDir );
				}
				if ( $processor instanceof ISpaceIdsDependentProcessor ) {
					$processor->setCurrentSpaceIds( $spaceIds );
				}
				$processor->execute();
			}

			// Add enhanced sidebar to the namespace directory, not shared. It is a namespace-scoped feature.
			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			if ( $sidebarProcessor instanceof ISubDirAware ) {
				$sidebarProcessor->setSubDir( $subDir );
			}
			$sidebarProcessor->execute();

			$this->writeSkippedPagesLog( $namespace, $namespaceDeploymentInfo, $subDir );
			$this->writeInvalidPagesLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidBlogPostsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidAttachmentsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidPageTemplatesLog( $spaceIds, $namespace, $subDir );

			$this->addSpaceImportHelper( $subDir );

			$wikiDeploymentInfo->addNamespace( $namespace );
			foreach ( $namespaceDeploymentInfo->getFileExtensions() as $extension ) {
				$wikiDeploymentInfo->addFileExtension( $extension );
			}

			if ( $this->isWorker() ) {
				// The wiki-level deployment.txt is deferred to the finalize pass (it may need
				// to merge contributions from other workers touching the same wiki). Send
				// this namespace's file extensions over the DB pipe (fd 3) so the orchestrator
				// can hand them to the finalize pass in memory — no temp files, mirroring how
				// Analyze/Convert stream results back to the parent process.
				$this->getPipeChannel()->send(
					[ 'addNamespaceExtensions', $subDir, $namespaceDeploymentInfo->getFileExtensions() ]
				);
			}
		}

		return $wikiDeploymentInfo;
	}

	/**
	 * @return PipeChannel
	 */
	private function getPipeChannel(): PipeChannel {
		if ( $this->pipeChannel === null ) {
			$this->pipeChannel = new PipeChannel();
		}
		return $this->pipeChannel;
	}

	/**
	 * Non-parallel pass run once after all compose workers have finished. Produces the
	 * once-per-wiki artifacts (shared content, wiki-level sidebar, deployment.txt,
	 * wikiimport.sh) that workers skipped while processing their namespace slices.
	 *
	 * @param array $wikiNames
	 * @param Builder $builder
	 * @return void
	 */
	private function finalizeWikis( array $wikiNames, Builder $builder ): void {
		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->dataLookup->getWikisConfigSpacesForWikiName( $wikiName );
			if ( $spaces === [] ) {
				continue;
			}

			$this->runSharedContentProcessors(
				$builder,
				$wikiName . '/_shared',
				array_map( 'intval', array_column( $spaces, 'space_id' ) )
			);

			$spacesMap = $this->buildSpacesMap( $spaces );
			$deploymentInfo = $this->mergeWikiDeploymentInfo( $spacesMap, $wikiName );

			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			$sidebarProcessor->setSubDir( $wikiName );
			$sidebarProcessor->execute();

			$this->addWikiImportHelper( $wikiName );
			$this->writeDeploymentLog( $deploymentInfo, $wikiName );
		}
	}

	/**
	 * Reconstruct the wiki-level ComposerDeploymentInfo from each namespace's file
	 * extensions, collected in memory from workers (via ComposeDataWriter) and passed
	 * through $this->namespaceFileExtensions, without re-running any content processing.
	 *
	 * @param array $spacesMap
	 * @param string $wikiName
	 * @return ComposerDeploymentInfo
	 */
	private function mergeWikiDeploymentInfo( array $spacesMap, string $wikiName ): ComposerDeploymentInfo {
		$deploymentInfo = new ComposerDeploymentInfo();

		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				continue;
			}
			$deploymentInfo->addNamespace( $namespace );

			$subDir = $wikiName . '/' . $namespace;
			foreach ( $this->namespaceFileExtensions[$subDir] ?? [] as $extension ) {
				$deploymentInfo->addFileExtension( $extension );
			}
		}

		return $deploymentInfo;
	}

	/**
	 * @param string $subDir
	 * @return void
	 */
	protected function addWikiImportHelper( string $subDir = '' ): void {
		$sourcePaths = glob( __DIR__ . '/_shell/*' );
		if ( $sourcePaths === false || $sourcePaths === [] ) {
			return;
		}

		$targetDir = $this->dest . "/result";
		if ( $subDir !== '' ) {
			$targetDir .= "/$subDir";
			$sourcePath = __DIR__ . '/_shell/wikiimport.sh';
			$this->copyShellScript( $sourcePath, $targetDir . '/wikiimport.sh' );
		}
	}
}
