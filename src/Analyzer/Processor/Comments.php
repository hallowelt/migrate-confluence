<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\SpaceFilter;
use XMLReader;

/**
 * Processor that reads Comment objects and collects metadata for page-level
 * (non-inline) comments, building the maps needed by the Composer to generate
 * Talk pages with CommentStreams data.
 */
class Comments extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @param SpaceFilter|null $spaceFilter
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer,
		private readonly ?SpaceFilter $spaceFilter = null
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(): void {
		$commentId = -1;
		$containerContentClass = '';
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if (
				$this->xmlReader->nodeType === XMLReader::ELEMENT &&
				$this->xmlReader->name === 'id' &&
				$this->xmlReader->getAttribute( 'name' ) === 'id'
			) {
				$commentId = (int)$this->xmlReader->readString();
			} elseif (
				$this->xmlReader->nodeType === XMLReader::ELEMENT &&
				$this->xmlReader->name === 'property'
			) {
				if ( $this->xmlReader->getAttribute( 'name' ) === 'containerContent' ) {
					$containerContentClass = $this->xmlReader->getAttribute( 'class' );
				}
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		if ( $commentId === -1 ) {
			return;
		}

		$contentStatus = $properties['contentStatus'] ?? null;

		// Only handle page-level comments (containerContent must be a Page)
		$containerContentId = isset( $properties['containerContent'] ) ? (int)$properties['containerContent'] : null;
		if ( $containerContentId === null ) {
			return;
		}

		if (
			$this->spaceFilter !== null &&
			!$this->spaceFilter->isContentAllowed( 'Comment', $commentId, $containerContentId )
		) {
			return;
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		} elseif ( isset( $properties['bodyContents'] ) ) {
			$bodyContentIds = $properties['bodyContents'];
		}
		// A fallback mechanism for body content IDs in case they are not found in the collection
		// and in collection is placed in the ConfluenceAnalyzer, which will attempt to retrieve them
		// from the body_contents table based on the comment ID.

		$this->output->writeln( "Add comment (ID:$commentId)" );

		if ( empty( $bodyContentIds ) ) {
			$warning = "Warning: No body content IDs found for comment (ID:$commentId)";
			$this->output->writeln( $warning );
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				$warning
			);
		}

		$creatorKey = $properties['creator'] ?? '';
		$created = $properties['creationDate'] ?? '';
		$modified = $properties['lastModificationDate'] ?? '';

		$status = $this->writer->addComment(
			$commentId,
			$containerContentId,
			$containerContentClass,
			$contentStatus,
			$creatorKey,
			$bodyContentIds,
			$this->buildTimestamp( $created ),
			$this->buildTimestamp( $modified ),
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
				"Comment ID $commentId already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}
	}
}
