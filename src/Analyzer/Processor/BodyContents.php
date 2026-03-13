<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class BodyContents extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-body-content-id-to-page-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$bodyContentId = '';
		$properties = [];
		$attributes = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$bodyContentId = (int)$this->getCDATAValue();
				} else {
					$bodyContentId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties, $attributes );
			}
			$this->xmlReader->next();
		}

		$pageId = (int)trim( $properties['content'] );

		$this->data['analyze-body-content-id-to-page-id-map'][$bodyContentId] = $pageId;
	}

}
