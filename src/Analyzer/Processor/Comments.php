<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

/**
 * Processor that reads Comment objects and collects metadata for page-level
 * (non-inline) comments, building the maps needed by the Composer to generate
 * Talk pages with CommentStreams data.
 */
class Comments extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [
			'analyze-inline-comment-ids',
			'analyze-body-content-id-to-comment-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-page-id-to-comment-ids-map',
			'global-comment-id-to-metadata-map',
			'global-body-content-id-to-comment-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Comment' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$status = $this->xmlHelper->getPropertyValue( 'contentStatus', $objectNode );
		if ( $status !== 'current' ) {
			return;
		}

		$commentId = $this->xmlHelper->getIDNodeValue( $objectNode );

		// Skip inline comments
		if ( in_array( $commentId, $this->data['analyze-inline-comment-ids'] ) ) {
			return;
		}

		// Only handle page-level comments (containerContent must be a Page)
		$containerContentNode = $this->xmlHelper->getPropertyNode( 'containerContent', $objectNode );
		if ( $containerContentNode === null ) {
			return;
		}
		if ( $containerContentNode->getAttribute( 'class' ) !== 'Page' ) {
			return;
		}

		$pageId = $this->xmlHelper->getPropertyValue( 'containerContent', $objectNode );
		if ( $pageId === null ) {
			return;
		}

		// Find the body content ID for this comment
		$commentToBodyContentMap = array_flip( $this->data['analyze-body-content-id-to-comment-id-map'] );
		if ( !isset( $commentToBodyContentMap[$commentId] ) ) {
			return;
		}
		$bodyContentId = $commentToBodyContentMap[$commentId];

		$creatorNode = $this->xmlHelper->getPropertyNode( 'creator', $objectNode );
		$creatorKey = null;
		if ( $creatorNode !== null ) {
			$idEl = $creatorNode->getElementsByTagName( 'id' )->item( 0 );
			if ( $idEl !== null ) {
				$creatorKey = $idEl->nodeValue;
			}
		}
		$created = $this->xmlHelper->getPropertyValue( 'creationDate', $objectNode );
		$modified = $this->xmlHelper->getPropertyValue( 'lastModificationDate', $objectNode );

		$this->data['global-comment-id-to-metadata-map'][$commentId] = [
			'page_id' => $pageId,
			'body_content_id' => $bodyContentId,
			'creator_key' => $creatorKey,
			'created' => $created,
			'modified' => $modified,
		];

		if ( !isset( $this->data['global-page-id-to-comment-ids-map'][$pageId] ) ) {
			$this->data['global-page-id-to-comment-ids-map'][$pageId] = [];
		}
		$this->data['global-page-id-to-comment-ids-map'][$pageId][] = $commentId;

		$this->data['global-body-content-id-to-comment-id-map'][$bodyContentId] = $commentId;
	}
}
