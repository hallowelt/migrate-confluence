<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class BodyContents extends ProcessorBase {
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
	public function doExecute(): void {
		$bodyContentId = '';
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$bodyContentId = $this->getCDATAValue();
				} else {
					$bodyContentId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$pageId = trim( $properties['content'] );
		$this->data['analyze-body-content-id-to-page-id-map'][$bodyContentId] = $pageId;
		/*
		$this->customBuckets->addData( 'analyze-body-content-id-to-page-id-map',
			$bodyContentId,	$pageId,	false, true );
		*/
	}

}
