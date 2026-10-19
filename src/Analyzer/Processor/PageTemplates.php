<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

class PageTemplates extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 */
	public function __construct( private IAnalyzeDataWriter $writer ) {
	}

	/**
	 * @return void
	 */
	public function doExecute(): void {
		$templateId = null;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$templateId = (int)$this->getCDATAValue();
				} else {
					$templateId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		if ( $templateId === null ) {
			Log::to( 'db' )->warning(
				'Page Template has no ID.'
			);

			return;
		}

		$name = $properties['name'] ?? '';
		if ( $name === '' ) {
			Log::to( 'db' )->warning(
				"Page Template with ID $templateId has no title."
			);

			return;
		}

		$spaceId = isset( $properties['space'] ) ? (int)$properties['space'] : null;
		if ( $spaceId === null ) {
			return;
		}

		$content = $properties['content'] ?? '';

		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';
		$contentStatus = $properties['contentStatus'] ?? 'current';

		$this->writer->addPageTemplateContents( $templateId, $content );

		unset( $properties['content'] );

		$status = $this->writer->addPageTemplate(
			$templateId,
			$name,
			$spaceId,
			'',
			$revisionTimestamp,
			$version,
			$properties,
			$collection,
			$contentStatus
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			$xmlDir = dirname( $xmlFile );
			$xmlFilename = basename( $xmlFile );
			Log::to( 'db' )->critical(
				"Page template ID $templateId ('$name') already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}

		Log::to( 'console' )->debug( "Add page template '$name' (ID:$templateId)" );
	}
}
