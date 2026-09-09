<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;

class WikiBasedComposer extends ConfluenceComposerBase {

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

		// Run shared content processors
		$sharedProcessors = $this->initProcessorsForSharedContent(
			$builder
		);

		foreach ( $sharedProcessors as $processor ) {
			$processor->setSubDir( '_shared' );
			$processor->execute();
		}

		// Run space dependent processors for each space
		// If wikis are configured, we will process spaces grouped by wiki name
		$this->output->writeln( "Data is assigned to some wikis." );
		$this->copySharedDirectoryToWikiDirectories( $wikiNames );

		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->dataLookup->getWikisConfigSpacesForWikiName( $wikiName );
			if ( $spaces === [] ) {
				$this->output->writeln( "No spaces found for wiki '$wikiName'." );
				continue;
			}

			$spacesMap = $this->buildSpacesMap( $spaces );
			$this->storeMigrationResult( $spacesMap, $builder, $wikiName );

			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			$sidebarProcessor->setSubDir( $wikiName );
			$sidebarProcessor->execute();

			$this->output->writeln( "Processing wiki '$wikiName' with " . count( $spaces ) . " spaces." );
		}

		$this->writeUserReadableDBLog( $this->dbLog );
	}

	/**
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @param string $wikiName
	 * @return void
	 */
	private function storeMigrationResult( array $spacesMap, Builder $builder, string $wikiName = '' ): void {
		$deploymentInfo = new ComposerDeploymentInfo();

		// Run processors for each namespace
		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip namespace '$namespace' by configuration." );
				continue;
			}
			$deploymentInfo->addNamespace( $namespace );

			if ( $wikiName === '' ) {
				$this->output->writeln( "Wikiname for namespace '$namespace' is empty. -> skipping" );
				continue;
			}
			$subDir = $wikiName . '/' . $namespace;

			$processors = $this->initProcessorsForSpaceContent( $builder, $deploymentInfo );

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

			$this->writeSkippedPagesLog( $namespace, $deploymentInfo, $subDir );
			$this->writeInvalidPagesLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidBlogPostsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidAttachmentsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidPageTemplatesLog( $spaceIds, $namespace, $subDir );

			$this->addSpaceImportHelper( $subDir );
		}

		$this->addWikiImportHelper( $wikiName );

		$this->writeDeploymentLog( $deploymentInfo, $wikiName );
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
