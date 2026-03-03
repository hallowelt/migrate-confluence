<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

class SpaceDescription extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-body-content-id-to-space-description-id-map'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'SpaceDescription' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$descId = $this->xmlHelper->getIDNodeValue( $objectNode );
		$bodyContents = $this->xmlHelper->getElementsFromCollection( 'bodyContents', $objectNode );
		foreach ( $bodyContents as $bodyContent ) {
			$bodyContentId = $this->xmlHelper->getIDNodeValue( $bodyContent );

			$this->data['global-body-content-id-to-space-description-id-map'][$bodyContentId] = $descId;
			$this->output->writeln( "\nAdd space description ($bodyContentId)" );
		}
	}

}
