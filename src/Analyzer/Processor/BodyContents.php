<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use Symfony\Component\Console\Output\OutputInterface;

class BodyContents  extends ProcessorBase {

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
		/*
		$this->customBuckets->addData( 'analyze-body-content-id-to-page-id-map',
			$bodyContentId,	$pageId,	false, true );
		*/
		$this->data['analyze-body-content-id-to-page-id-map'][$bodyContentId] = trim( $pageId );
	}

}
