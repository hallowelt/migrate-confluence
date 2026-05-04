<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

/**
 * Processor that reads Comment objects and collects metadata for page-level
 * (non-inline) comments, building the maps needed by the Composer to generate
 * Talk pages with CommentStreams data.
 */
class Comments extends ProcessorBase {

	/**
	 * @param ConfigDB $configDB
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private ConfigDB $configDB,
		private WorkspaceDB $workspaceDB
	) {}

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

		$contentStatus = $properties['contentStatus'] ?? null;

		if ( $commentId === -1 ) {
			return;
		}

		// Only handle page-level comments (containerContent must be a Page)
		$containerContentId = isset( $properties['containerContent'] ) ? (int)$properties['containerContent'] : null;
		if ( $containerContentId === null ) {
			#return;
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		} else {
			$this->output->writeln( "Use fallback to fetch body content IDs (ID:$commentId)" );
			$bodyContentIds = $this->workspaceDB->getBodyContentIdsForPageId( $commentId );
		}

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

		$this->workspaceDB->addComment(
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
	}
}
