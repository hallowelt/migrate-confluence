<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

class BodyContents extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-body-content-id-to-page-id-map'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'BodyContent' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}
		$bodyContentId = $this->xmlHelper->getIDNodeValue( $objectNode );
		$pageId = $this->xmlHelper->getPropertyValue( 'content', $objectNode );

		$this->data['analyze-body-content-id-to-page-id-map'][$bodyContentId] = trim( $pageId );
	}

}
