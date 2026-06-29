<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\AnalyzeWorkerDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

/**
 * Processor that reads Comment objects and collects metadata for page-level
 * (non-inline) comments, building the maps needed by the Composer to generate
 * Talk pages with CommentStreams data.
 */
class Comments extends ProcessorBase {

	/**
	 * @param WorkspaceDB|AnalyzeWorkerDB $workspaceDB
	 */
	public function __construct(
		private WorkspaceDB|AnalyzeWorkerDB $workspaceDB
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(): void {
		$commentId = -1;
		$containerContentClass = '';
		$properties = [];

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

		$bodyContentIds = [];
		if ( isset( $properties['bodyContents'] ) ) {
			$bodyContentIds = $properties['bodyContents'];
		}
		// A fallback mechanism for body content IDs in case they are not found in the collection
		// is placed in the ConfluenceAnalyzer, which will attempt to retrieve them from the
		// body_contents table based on the comment ID.

		$this->output->writeln( "Add comment (ID:$commentId)" );

		if ( empty( $bodyContentIds ) ) {
			$warning = "Warning: No body content IDs found for comment (ID:$commentId)";
			$this->output->writeln( $warning );
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				$warning
			);
		}

		$creatorKey = $properties['creator'] ?? null;
		$created = $properties['creationDate'] ?? '';
		$modified = $properties['lastModificationDate'] ?? '';

		$status = $this->workspaceDB->addComment(
			$commentId,
			$containerContentId,
			$containerContentClass,
			$contentStatus,
			$creatorKey,
			$bodyContentIds,
			$this->buildTimestamp( $created ),
			$this->buildTimestamp( $modified ),
			$properties
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add comment (ID:$commentId) to the database."
			);
		}
	}
}
