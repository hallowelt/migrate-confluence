<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

class BlogPost extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/** @var array */
	private $includeSpaceKey = [];

	/** @var bool */
	private $includeHistory = false;

	/** @var mixed */
	private $spaceId;

	/** @var mixed */
	private $pageId;

	/** @var string */
	private $targetTitle = '';

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
			'analyze-pages-titles-map',
			'analyze-page-id-to-confluence-key-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'debug-analyze-invalid-titles-page-id-to-title',
			'analyze-page-id-to-confluence-key-map',
			'analyze-pages-titles-map',
			'analyze-page-id-to-title-map',
			'analyze-title-revisions',
			'global-page-id-to-space-id',
			'global-body-content-id-to-page-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'BlogPost' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}
		$status = $this->xmlHelper->getPropertyValue( 'contentStatus', $objectNode );
		if ( !$this->includeHistory && ( $status !== 'current' ) ) {
			return;
		}
		$this->spaceId = $this->xmlHelper->getPropertyValue( 'space', $objectNode );
		if ( $this->spaceId === null ) {
			return;
		}
		if ( !isset( $this->data['global-space-id-to-key-map'][$this->spaceId] ) ) {
			return;
		}
		$spaceKey = $this->data['global-space-id-to-key-map'][$this->spaceId];

		if (
			!empty( $this->includeSpaceKey )
			&& !in_array( strtolower( $spaceKey ), $this->includeSpaceKey )
		) {
			return;
		}
		$originalVersionID = $this->xmlHelper->getPropertyValue( 'originalVersion', $objectNode );
		if ( $originalVersionID !== null ) {
			return;
		}

		$this->pageId = $this->xmlHelper->getIDNodeValue( $objectNode );

		$rawTitle = $this->xmlHelper->getPropertyValue( 'title', $objectNode );
		$sanitizedTitle = str_replace(
			[ ':', '%', '?', '#', '<', '>', '+', '[', ']', '{', '}', '|' ],
			'_',
			$rawTitle
		);
		$sanitizedTitle = str_replace( '__', '_', $sanitizedTitle );
		$this->targetTitle = 'Blog:General/' . $sanitizedTitle;

		if ( $this->targetTitle === 'Blog:General/' ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => $this->targetTitle
			];
			return;
		}

		$this->output->writeln( "Add blog post '$this->targetTitle' (ID:$this->pageId)" );

		$this->process( $objectNode );
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function process( DOMElement $node ): void {
		$pageConfluenceTitle = $this->xmlHelper->getPropertyValue( 'title', $node );
		$genericTitleBuilder = new GenericTitleBuilder( [] );
		$pageConfluenceTitle = $genericTitleBuilder
			->appendTitleSegment( $pageConfluenceTitle )->build();
		$pageConfluenceTitle = "$this->spaceId---{$pageConfluenceTitle}";
		$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
		$this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] = $pageConfluenceTitle;
		$this->data['analyze-pages-titles-map'][$pageConfluenceTitle] = $this->targetTitle;
		$this->data['analyze-page-id-to-title-map'][$this->pageId] = $this->targetTitle;
		$this->data['global-page-id-to-space-id'][$this->pageId] = $this->spaceId;

		$revisionTimestamp = $this->buildRevisionTimestamp( $this->xmlHelper, $node );
		$bodyContentIds = $this->getBodyContentIds( $this->xmlHelper, $node );
		if ( !empty( $bodyContentIds ) ) {
			foreach ( $bodyContentIds as $bodyContentId ) {
				$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
			}
		} else {
			$bodyContentIds = [];
			foreach ( $this->data['analyze-body-content-id-to-page-id-map'] as $bodyContentId => $contentPageId ) {
				if ( $this->pageId === $contentPageId ) {
					$bodyContentIds[] = $bodyContentId;
					$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
				}
			}
		}

		$version = $this->xmlHelper->getPropertyValue( 'version', $node );
		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";
		$this->data['analyze-title-revisions'][$this->targetTitle][] = $revision;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return string
	 */
	private function buildRevisionTimestamp( XMLHelper $xmlHelper, DOMElement $pageNode ): string {
		$lastModificationDate = $xmlHelper->getPropertyValue( 'lastModificationDate', $pageNode );
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );
		return $mwTimestamp;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return array
	 */
	private function getBodyContentIds( XMLHelper $xmlHelper, DOMElement $pageNode ): array {
		$ids = [];
		$bodyContentEl = $xmlHelper->getElementsFromCollection( 'bodyContents', $pageNode );
		foreach ( $bodyContentEl as $bodyContentElement ) {
			$ids[] = $xmlHelper->getIDNodeValue( $bodyContentElement );
		}
		return $ids;
	}
}
