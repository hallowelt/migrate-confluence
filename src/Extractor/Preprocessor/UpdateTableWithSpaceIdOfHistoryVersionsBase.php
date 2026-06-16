<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Base class for preprocessors that propagate a non-null space_id from
 * the original version of a page/blog-post to all its historical versions.
 *
 * Space id of historical versions can be null but we need the space id for
 * the converter. This class reads all rows, builds an id→space_id map from
 * the rows that already have a space_id, then updates the rows whose
 * space_id is still null.
 */
abstract class UpdateTableWithSpaceIdOfHistoryVersionsBase extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$pageIdToSpaceIdMap = [];
		$rows = $this->getRows();

		foreach ( $rows as $row ) {
			if ( !isset( $row['page_id'] ) || !array_key_exists( 'space_id', $row ) ) {
				continue;
			}

			if ( $row['space_id'] === null ) {
				continue;
			}

			$pageIdToSpaceIdMap[(int)$row['page_id']] = (int)$row['space_id'];
		}

		foreach ( $rows as $row ) {
			if (
				!isset( $row['page_id'] )
				|| !array_key_exists( 'space_id', $row )
				|| !isset( $row['original_version_id'] )
			) {
				continue;
			}

			$originalVersionId = (int)$row['original_version_id'];
			if ( $originalVersionId === -1 ) {
				continue;
			}

			if ( $row['space_id'] !== null ) {
				continue;
			}

			$pageId = (int)$row['page_id'];

			if ( !isset( $pageIdToSpaceIdMap[$originalVersionId] ) ) {
				continue;
			}

			$originalSpaceId = (int)$pageIdToSpaceIdMap[$originalVersionId];

			$this->updateSpaceId( $pageId, $originalSpaceId );
			$this->writeln(
				"Updated space_id for historical {$this->getContentLabel()} ID $pageId"
				. " with space_id: $originalSpaceId"
			);
		}
	}

	/**
	 * Returns all rows for the content type (pages or blog posts).
	 * Each row must contain at least 'page_id', 'space_id', and 'original_version_id'.
	 *
	 * @return array
	 */
	abstract protected function getRows(): array;

	/**
	 * Returns a human-readable label for the content type used in log messages.
	 * E.g. "page" or "blog post".
	 *
	 * @return string
	 */
	abstract protected function getContentLabel(): string;

	/**
	 * Persists the resolved space_id for a single row.
	 *
	 * @param int $pageId
	 * @param int $spaceId
	 * @return void
	 */
	abstract protected function updateSpaceId( int $pageId, int $spaceId ): void;
}
