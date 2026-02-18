<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

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
	public function doExecute(): void {
		$pageId = '';
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$pageId = $this->getCDATAValue();
				} else {
					$pageId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$status = null;
		if ( isset( $properties['contentStatus'] ) ) {
			$status = $properties['contentStatus'];
		}
		if ( strtolower( $status ) !== 'current' ) {
			return;
		}

		$spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$spaceId = $properties['space'];
		}
		if ( $spaceId === null ) {
			return;
		}

		$originalVersionID = null;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionID = $properties['originalVersion'];
		}
		if ( $originalVersionID !== null ) {
			return;
		}

		$parentPageId = null;
		if ( isset( $properties['parent'] ) ) {
			$parentPageId = $properties['parent'];
		}
		if ( $parentPageId !== null ) {
			/*
			$this->customBuckets->addData(
				'analyze-page-id-to-parent-page-id-map',
				$pageId, $parentPageId, false, true
			);
			*/
			$this->data['analyze-page-id-to-parent-page-id-map'][$pageId] = trim( $parentPageId );
		}

		$confluenceTitle = null;
		if ( isset( $properties['title'] ) ) {
			$confluenceTitle = $properties['title'];
		}
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
