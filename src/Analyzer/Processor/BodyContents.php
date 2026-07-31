<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

class BodyContents extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$bodyContentId = -1;
		$properties = [];
		$contentClass = '';

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$bodyContentId = (int)$this->getCDATAValue();
				} else {
					$bodyContentId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'content' ) {
					$contentClass = $this->xmlReader->getAttribute( 'class' ) ?? '';
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( !isset( $properties['content'] ) ) {
			return;
		}

		// The body will be extracted later as file for pandoc and does not need to be in database.
		// We store it in a separate table to be able to easily retrieve it for the content transformation and
		// to keep the main table smaller.
		if ( isset( $properties['body'] ) ) {
			$this->writer->addBodyContentBody(
				$bodyContentId,
				$properties['body']
			);
			unset( $properties['body'] );
		}

		$contentId = (int)trim( $properties['content'] );

		$status = $this->writer->addBodyContent(
			$bodyContentId,
			$contentId,
			$contentClass,
			$properties
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			$xmlDir = dirname( $xmlFile );
			$xmlFilename = basename( $xmlFile );
			$this->writer->addLogEntry(
				'serious-error',
				'analyze',
				__CLASS__,
				"Body content ID $bodyContentId already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}
	}

}
