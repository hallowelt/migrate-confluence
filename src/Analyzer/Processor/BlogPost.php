<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use XMLReader;

class BlogPost extends ProcessorBase {

	/** @var string */
	private const NS_BLOG_NAME = 'Blog';

	/** @var array */
	private array $includeSpaceKey;

	/** @var bool */
	private bool $includeHistory;

	/** @var mixed */
	private $spaceId;

	/** @var mixed */
	private $pageId;

	/** @var string */
	private string $targetTitle = '';

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

		if ( !$this->configDB->getIncludeHistory() && ( strtolower( $contentStatus ) !== 'current' ) ) {
			return;
		}

		$spaceId = -1;
		if ( isset( $properties['space'] ) ) {
			$spaceId = (int)$properties['space'];
		}
		if ( $spaceId === null ) {
			return;
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
		}
		// TODO: Add fallback if bodyContentIds is empty

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$lastModificationDate = $properties['lastModificationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );


		/*
		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";
		*/

		$this->output->writeln( "Add blog post '$confluenceTitle' (ID:$pageId)" );

		$this->workspaceDB->addBlogPost(
			$pageId,
			$spaceId,
			$confluenceTitle,
			'',
			$revisionTimestamp,
			$contentStatus,
			$originalVersionId,
			$bodyContentIds,
			$properties,
			$collection
		);
	}
}
