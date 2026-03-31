<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
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
	 * @param array $includeSpaceKey
	 * @param bool $includeHistory
	 */
	public function __construct(
		array $includeSpaceKey,
		bool $includeHistory
	) {
		$this->includeSpaceKey = $includeSpaceKey;
		$this->includeHistory = $includeHistory;
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [
			'global-space-id-to-key-map',
			'analyze-body-content-id-to-page-id-map',
			'analyze-blogposts-titles-map',
			'analyze-blogpost-id-to-confluence-key-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'debug-analyze-invalid-titles-page-id-to-title',
			'analyze-blogpost-id-to-confluence-key-map',
			'analyze-blogposts-titles-map',
			'analyze-blogpost-id-to-title-map',
			'analyze-title-revisions',
			'global-blogpost-id-to-space-id',
			'global-body-content-id-to-page-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$this->pageId = (int)$this->getCDATAValue();
				} else {
					$this->pageId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		$status = null;
		if ( isset( $properties['contentStatus'] ) ) {
			$status = $properties['contentStatus'];
		}
		if ( !$this->includeHistory && ( $status !== 'current' ) ) {
			return;
		}

		$this->spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$this->spaceId = (int)$properties['space'];
		}
		if ( $this->spaceId === null ) {
			return;
		}

		if ( !isset( $this->data['global-space-id-to-key-map'][$this->spaceId] ) ) {
			return;
		}
		$spaceKey = $this->data['global-space-id-to-key-map'][$this->spaceId];

		if (
			!empty( $this->includeSpaceKey ) && !in_array( strtolower( $spaceKey ), $this->includeSpaceKey )
		) {
			return;
		}

		$originalVersionID = null;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionID = $properties['originalVersion'];
		}
		if ( $originalVersionID !== null ) {
			return;
		}

		$title = $properties['title'] ?? "";
		if ( empty( $title ) ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => "Invalid source title"
			];

			return;
		}

		$blogName = self::NS_BLOG_NAME;
		$titleBuilder = new TitleBuilder( [ $this->spaceId => "$blogName:$spaceKey/" ], [], [], [], );

		try {
			$this->targetTitle = $titleBuilder->buildTitle( $this->spaceId, $this->pageId, $title );
		} catch ( InvalidTitleException $e ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => $e->getInvalidTitle()
			];

			return;
		}

		$this->output->writeln( "Add blog post '$this->targetTitle' (ID:$this->pageId)" );

		$this->process( $title, $properties, $collection );
	}

	/**
	 * @param string $title
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return void
	 * @throws InvalidTitleException
	 */
	private function process( string $title, array $properties, array $collection ): void {
		$genericTitleBuilder = new GenericTitleBuilder( [] );
		$pageConfluenceTitle = $genericTitleBuilder->appendTitleSegment( $title )->build();
		$pageConfluenceTitle = "$this->spaceId---$pageConfluenceTitle";
		$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );

		$this->data['analyze-blogpost-id-to-confluence-key-map'][$this->pageId] = $pageConfluenceTitle;
		$this->data['analyze-blogposts-titles-map'][$pageConfluenceTitle] = $this->targetTitle;
		$this->data['analyze-blogpost-id-to-title-map'][$this->pageId] = $this->targetTitle;
		$this->data['global-blogpost-id-to-space-id'][$this->pageId] = $this->spaceId;

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$lastModificationDate = $properties['lastModificationDate'];
		}
		$revisionTimestamp = $this->buildRevisionTimestamp( $lastModificationDate );

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		}

		if ( !empty( $bodyContentIds ) ) {
			foreach ( $bodyContentIds as $bodyContentId ) {
				$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
			}
		} else {
			foreach ( $this->data['analyze-body-content-id-to-page-id-map'] as $bodyContentId => $contentPageId ) {
				if ( $this->pageId === $contentPageId ) {
					$bodyContentIds[] = $bodyContentId;
					$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
				}
			}
		}

		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";
		$this->data['analyze-title-revisions'][$this->targetTitle][] = $revision;
	}

	/**
	 * @param string $lastModificationDate
	 *
	 * @return string
	 */
	private function buildRevisionTimestamp( string $lastModificationDate ): string {
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );

		return $mwTimestamp;
	}
}
