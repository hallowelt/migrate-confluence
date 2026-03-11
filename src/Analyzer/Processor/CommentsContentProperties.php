<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

/**
 * Preprocessor that reads ContentProperty objects linked to Comment objects
 * and collects the IDs of inline comments (identified by having an
 * "inline-comment" or "inline-marker-ref" property).
 */
class CommentsContentProperties extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-inline-comment-ids',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'ContentProperty' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$contentPropertyNode = $this->xmlHelper->getPropertyNode( 'content', $objectNode );
		if ( $contentPropertyNode === null ) {
			return;
		}
		if ( $contentPropertyNode->getAttribute( 'class' ) !== 'Comment' ) {
			return;
		}

		$propName = $this->xmlHelper->getPropertyValue( 'name', $objectNode );
		if ( $propName !== 'inline-comment' && $propName !== 'inline-marker-ref' ) {
			return;
		}

		$commentId = $this->xmlHelper->getIDNodeValue( $contentPropertyNode );
		if ( $commentId === -1 ) {
			return;
		}

		if ( !in_array( $commentId, $this->data['analyze-inline-comment-ids'] ) ) {
			$this->data['analyze-inline-comment-ids'][] = $commentId;
		}
	}
}
