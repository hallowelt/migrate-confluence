<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class BodyContents extends ProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-body-content-id-to-page-id-map',
			'analyze-body-content-id-to-comment-id-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$bodyContentId = '';
		$properties = [];
		$contentClass = '';

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$bodyContentId = (int)$this->getCDATAValue();
				} else {
					$bodyContentId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'content' ) {
					$contentClass = $this->xmlReader->getAttribute( 'class' ) ?? '';
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$contentId = (int)trim( $properties['content'] );

		if ( $contentClass === 'Comment' ) {
			$this->data['analyze-body-content-id-to-comment-id-map'][$bodyContentId] = $contentId;
		} else {
			$this->data['analyze-body-content-id-to-page-id-map'][$bodyContentId] = $contentId;
		}
	}

}
