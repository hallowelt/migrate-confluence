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

		$fileExtensionsPerWiki = [];
		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->dataLookup->getWikisConfigSpacesForWikiName( $wikiName );
			if ( $spaces === [] ) {
				$this->output->writeln( "No spaces found for wiki '$wikiName'." );
				continue;
			}

			$spacesMap = $this->buildSpacesMap( $spaces );
			$fileExtensionsPerWiki[$wikiName] = $this->storeMigrationResult( $spacesMap, $builder, $wikiName );

			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			$sidebarProcessor->setSubDir( $wikiName );
			$sidebarProcessor->execute();

			$this->output->writeln( "Processing wiki '$wikiName' with " . count( $spaces ) . " spaces." );
		}

		$this->writeUserReadableDBLog( $this->dbLog );
		$this->writeManifest( $fileExtensionsPerWiki );
	}

	/**
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @param string $wikiName
	 * @return array The file extensions collected for this wiki
	 */
	private function storeMigrationResult( array $spacesMap, Builder $builder, string $wikiName = '' ): array {
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

		return $deploymentInfo->getFileExtensions();
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

	/**
	 * Writes a "manifest.json" file to the result directory, describing the deployable
	 * wikis produced by a multi-wiki (wiki-based) migration run.
	 *
	 * @param array $fileExtensionsPerWiki Map of wiki name to the file extensions used in that wiki
	 * @return void
	 */
	private function writeManifest( array $fileExtensionsPerWiki ): void {
		if ( $fileExtensionsPerWiki === [] ) {
			return;
		}

		$wikis = [];
		foreach ( $fileExtensionsPerWiki as $wikiName => $fileExtensions ) {
			$scripts = [];
			foreach ( $this->getManifestScriptTemplates() as $scriptTemplate ) {
				$scripts[] = str_replace( '<instance_id>', $wikiName, $scriptTemplate );
			}

			$wikis[] = [
				'sfr' => $wikiName,
				'file_extensions' => $fileExtensions,
				'scripts' => $scripts,
			];
		}

		$manifest = [
			'source_system' => 'confluence',
			'package_id' => $this->makePackageId(),
			'target' => [
				'wikis' => $wikis,
			],
		];

		file_put_contents(
			$this->dest . '/result/manifest.json',
			json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
		);
	}

	/**
	 * @return string[]
	 */
	private function getManifestScriptTemplates(): array {
		return [
			'./result/<instance_id>/wikiimport.sh --sfr=<instance_id> --add-defaults',
			'php /app/bluespice/w/maintenance/rebuildall.php --sfr=<instance_id>',
		];
	}

	/**
	 * Builds a package id from the name of the parent directory of the workspace
	 * (the migration project directory) and the current date, e.g.
	 * "customer-x-2026-08-10-v1". Falls back to "migration" if the parent
	 * directory name cannot be determined.
	 *
	 * @return string
	 */
	private function makePackageId(): string {
		$parentDirName = basename( dirname( rtrim( $this->dest, '/' ) ) );
		if ( $parentDirName === '' || $parentDirName === '.' || $parentDirName === '/' || $parentDirName === false ) {
			$parentDirName = 'migration';
		}

		return $parentDirName . '-' . date( 'Y-m-d' );
	}
}
