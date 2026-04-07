<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

/**
 * Processor that reads Comment objects and collects metadata for page-level
 * (non-inline) comments, building the maps needed by the Composer to generate
 * Talk pages with CommentStreams data.
 */
class Comments extends ProcessorBase {

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
	protected function doExecute(): void {
		$commentId = -1;
		$containerContentClass = null;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if (
				$this->xmlReader->nodeType === XMLReader::ELEMENT &&
				$this->xmlReader->name === 'id' &&
				$this->xmlReader->getAttribute( 'name' ) === 'id'
			) {
				$commentId = (int)$this->xmlReader->readString();
			} elseif (
				$this->xmlReader->nodeType === XMLReader::ELEMENT &&
				$this->xmlReader->name === 'property'
			) {
				if ( $this->xmlReader->getAttribute( 'name' ) === 'containerContent' ) {
					$containerContentClass = $this->xmlReader->getAttribute( 'class' );
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$status = $properties['contentStatus'] ?? null;
		if ( $status !== 'current' ) {
			return;
		}

		if ( $commentId === -1 ) {
			return;
		}

		// Skip inline comments
		if ( in_array( $commentId, $this->data['analyze-inline-comment-ids'] ) ) {
			return;
		}

		// Only handle page-level comments (containerContent must be a Page)
		if ( $containerContentClass !== 'Page' ) {
			return;
		}

		$pageId = isset( $properties['containerContent'] ) ? (int)$properties['containerContent'] : null;
		if ( $pageId === null ) {
			return;
		}

		// Find the body content ID for this comment
		$commentToBodyContentMap = array_flip( $this->data['analyze-body-content-id-to-comment-id-map'] );
		if ( !isset( $commentToBodyContentMap[$commentId] ) ) {
			return;
		}
		$bodyContentId = $commentToBodyContentMap[$commentId];

		$creatorKey = $properties['creator'] ?? null;
		$created = $properties['creationDate'] ?? '';
		$modified = $properties['lastModificationDate'] ?? '';

		$this->data['global-comment-id-to-metadata-map'][$commentId] = [
			'page_id' => $pageId,
			'body_content_id' => $bodyContentId,
			'creator_key' => $creatorKey,
			'created' => $this->buildTimestamp( $created ),
			'modified' => $this->buildTimestamp( $modified ),
		];

		if ( !isset( $this->data['global-page-id-to-comment-ids-map'][$pageId] ) ) {
			$this->data['global-page-id-to-comment-ids-map'][$pageId] = [];
		}
		$this->data['global-page-id-to-comment-ids-map'][$pageId][] = $commentId;

		$this->data['global-body-content-id-to-comment-id-map'][$bodyContentId] = $commentId;
	}
}
