<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

class ParentPages  extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-page-id-to-parent-page-id-map',
			'analyze-page-id-to-confluence-title-map'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Page' );
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
		$spaceId = $this->xmlHelper->getPropertyValue( 'space', $objectNode );
		if ( $spaceId === null ) {
			return;
		}
		$originalVersionID = $this->xmlHelper->getPropertyValue( 'originalVersion', $objectNode );
		if ( $originalVersionID !== null ) {
			return;
		}
		$pageId = $this->xmlHelper->getIDNodeValue( $objectNode );
		$parentPageId = $this->xmlHelper->getPropertyValue( 'parent', $objectNode );
		if ( $parentPageId !== null ) {
			/*
			$this->customBuckets->addData(
				'analyze-page-id-to-parent-page-id-map',
				$pageId, $parentPageId, false, true
			);
			*/
			$this->data['analyze-page-id-to-parent-page-id-map'][$pageId] = trim( $parentPageId );
		}

		$pageId = $this->xmlHelper->getIDNodeValue( $objectNode );
		$confluenceTitle = $this->xmlHelper->getPropertyValue( 'title', $objectNode );
		if ( $confluenceTitle !== null ) {
			/*
			$this->customBuckets->addData(
				'analyze-page-id-to-confluence-title-map',
				$pageId, $confluenceTitle, false, true
			);
			*/
			$this->data['analyze-page-id-to-confluence-title-map'][$pageId] = $confluenceTitle;
		}
	}

}
