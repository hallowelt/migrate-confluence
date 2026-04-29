<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class Page extends ProcessorBase {

	/**
	 * @param ConfigDB $configDB
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private ConfigDB $configDB,
		private WorkspaceDB $workspaceDB
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
					$pageId = $this->getCDATAValue();
				} else {
					$pageId = $this->getTextValue();
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

		if ( !$this->configDB->getIncludeHistory() && ( strtolower( $contentStatus ) !== 'current' ) ) {
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
			// TODO: log page id with empty title
			return;
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		} else {
			$bodyContentIds[] = $this->workspaceDB->getBodyContentIdsForPageId( $pageId );
		}
		// TODO: What if there are no body content id's?

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$lastModificationDate = $properties['lastModificationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );

		$parentPageId = -1;
		if ( isset( $properties['parent'] ) ) {
			$parentPageId = $properties['parent'];
		}
		
		/*
		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";
		*/

		$this->output->writeln( "Add page '$confluenceTitle' (ID:$pageId)" );

		$this->workspaceDB->addPage(
			$pageId,
			$spaceId,
			$confluenceTitle,
			'',
			$revisionTimestamp,
			$contentStatus,
			$originalVersionId,
			$parentPageId,
			$bodyContentIds,
			$properties,
			$collection
		);

	}

}
