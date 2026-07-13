<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use XMLReader;

class BlogPost extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer,
		private MigrationConfig $migrationConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$pageId = -1;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$pageId = (int)$this->getCDATAValue();
				} else {
					$pageId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		$contentStatus = '';
		if ( isset( $properties['contentStatus'] ) ) {
			$contentStatus = $properties['contentStatus'];
		}

		$spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$spaceId = (int)$properties['space'];
		}

		$originalVersionId = -1;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionId = (int)$properties['originalVersion'];
		}

		if ( !$this->migrationConfig->getIncludeHistory() && $originalVersionId > 0 ) {
			return;
		}

		$confluenceTitle = $properties['title'] ?? "";
		if ( empty( $confluenceTitle ) ) {
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Blog post with ID $pageId has empty title. Skipping."
			);
			return;
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		}
		// A fallback mechanism for body content IDs in case they are not found in the collection
		// is placed in the ConfluenceAnalyzer, which will attempt to retrieve them from
		// the body_contents table based on the blog post ID.

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) && $properties['lastModificationDate'] !== '' ) {
			$lastModificationDate = $properties['lastModificationDate'];
		} elseif ( isset( $properties['creationDate'] ) && $properties['creationDate'] !== '' ) {
			$lastModificationDate = $properties['creationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );

		$lastModifier = '';
		if ( isset( $properties['lastModifier'] ) && $properties['lastModifier'] !== '' ) {
			$lastModifier = $properties['lastModifier'];
		} elseif ( isset( $properties['creator'] ) && $properties['creator'] !== '' ) {
			$lastModifier = $properties['creator'];
		}

		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$this->output->writeln( "Add blog post '$confluenceTitle' (ID:$pageId)" );

		if ( empty( $bodyContentIds ) ) {
			$warning = "Warning: No body content IDs found for page '$confluenceTitle' (ID:$pageId)";
			$this->output->writeln( $warning );
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				$warning
			);
		}

		$historicalIds = [];
		if ( isset( $collection['historicalVersions'] ) ) {
			$historicalIds = $collection['historicalVersions'];
		}

		$status = $this->writer->addBlogPost(
			$pageId,
			$spaceId,
			$confluenceTitle,
			'',
			$contentStatus,
			$revisionTimestamp,
			$lastModifier,
			$version,
			$originalVersionId,
			$bodyContentIds,
			$historicalIds,
			$properties,
			$collection
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			$xmlDir = dirname( $xmlFile );
			$xmlFilename = basename( $xmlFile );
			$this->writer->addLogEntry(
				'serious-error',
				'analyze',
				__CLASS__,
				"Blog post ID $pageId ('$confluenceTitle') already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}
	}
}
