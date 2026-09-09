<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;

class NamespaceBasedComposer extends ConfluenceComposerBase {

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function doBuildXML( Builder $builder ): void {
		$wikiNames = $this->dataLookup->getWikisConfigWikiNames();
		if ( $wikiNames !== [] ) {
			// This composer is only for space-based deployments. If wikis are configured, we will not process anything.
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
		$this->output->writeln( "Data is not assigned to any wikis." );

		$spaces = $this->dataLookup->getSpaces();
		if ( $spaces === [] ) {
			$this->output->writeln( "No spaces found." );
		}

		$spacesMap = $this->buildSpacesMap( $spaces );
		$this->storeMigrationResult( $spacesMap, $builder );

		$this->writeUserReadableDBLog( $this->dbLog );
	}

	/**
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @return void
	 */
	protected function storeMigrationResult( array $spacesMap, Builder $builder ): void {
		// Run processors for each namespace
		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip namespace '$namespace' by configuration." );
				continue;
			}

			$deploymentInfo = new ComposerDeploymentInfo();
			$deploymentInfo->addNamespace( $namespace );

			$subDir = $namespace;

			$processors = $this->initProcessorsForSpaceContent( $builder, $deploymentInfo );

			$spaceIds = array_keys( $spaces );
			foreach ( $processors as $processor ) {
				$processor->setSubDir( $subDir );
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

			$this->writeDeploymentLog( $deploymentInfo, $subDir );

			$this->addSpaceImportHelper( $subDir );
		}
	}

}
