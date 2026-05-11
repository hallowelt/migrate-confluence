<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use XMLReader;

class Page extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB,
		private MigrationConfig $migrationConfig
	) {}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$pageId = -1;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$pageId = (int)$this->getCDATAValue();
				} else {
					$pageId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		$contentStatus = null;
		if ( isset( $properties['contentStatus'] ) ) {
			$contentStatus = $properties['contentStatus'];
		}

		if ( !$this->migrationConfig->getIncludeHistory() && ( strtolower( $contentStatus ) !== 'current' ) ) {
			return;
		}

		$spaceId = -1;
		if ( isset( $properties['space'] ) ) {
			$spaceId = (int)$properties['space'];
		}

		$originalVersionId = -1;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionId = (int)$properties['originalVersion'];
		}
		if ( $originalVersionId !== -1 ) {
			return;
		}

		$confluenceTitle = $properties['title'] ?? "";
		if ( empty( $confluenceTitle ) ) {
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page with ID $pageId has no title"
			);
			return;
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		}
		// A fallback mechanism for body content IDs in case they are not found in the collection
		// is placed in the ConfluenceAnalyzer, which will attempt to retrieve them from the
		// body_contents table based on the page ID.

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$lastModificationDate = $properties['lastModificationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );

		$parentPageId = -1;
		if ( isset( $properties['parent'] ) ) {
			$parentPageId = $properties['parent'];
		}
		
		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$this->output->writeln( "Add page '$confluenceTitle' (ID:$pageId)" );

		if ( empty( $bodyContentIds ) ) {
			$warning = "Warning: No body content IDs found for page '$confluenceTitle' (ID:$pageId)";
			$this->output->writeln( $warning );
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				$warning
			);
		}

		$status = $this->workspaceDB->addPage(
			$pageId,
			$spaceId,
			$confluenceTitle,
			'',
			$revisionTimestamp,
			strtolower( $contentStatus ),
			$version,
			$originalVersionId,
			$parentPageId,
			$bodyContentIds,
			$properties,
			$collection
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add page '$confluenceTitle' (ID:$pageId) to the database."
				. " This may indicate a problem with the page id. Maybe it does exist twice."
			);
		}
	}

}
