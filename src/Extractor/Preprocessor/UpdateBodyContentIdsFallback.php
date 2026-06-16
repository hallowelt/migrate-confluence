<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Fallback to set valid body content id's. This is sometimes
 * required.
 */
class UpdateBodyContentIdsFallback extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->updateBodyContentIdsForRows(
			$this->workspaceDB->getPages(),
			'page_id',
			'page',
			fn ( int $id, array $ids ) => $this->workspaceDB->updatePageBodyContentIds( $id, $ids )
		);

		$this->updateBodyContentIdsForRows(
			$this->workspaceDB->getBlogPosts(),
			'page_id',
			'blog post',
			fn ( int $id, array $ids ) => $this->workspaceDB->updateBlogPostBodyContentIds( $id, $ids )
		);

		$this->updateBodyContentIdsForRows(
			$this->workspaceDB->getComments(),
			'comment_id',
			'comment',
			fn ( int $id, array $ids ) => $this->workspaceDB->updateCommentBodyContentIds( $id, $ids )
		);

		$this->updateBodyContentIdsForRows(
			$this->workspaceDB->getSpaceDescriptions(),
			'space_description_id',
			'space description',
			fn ( int $id, array $ids ) => $this->workspaceDB->updateSpaceDescriptionBodyContentIds( $id, $ids )
		);
	}

	/**
	 * For each row whose body_content_ids is empty, look up the IDs from the body_contents
	 * table and persist them via the provided update callback.
	 *
	 * @param array $rows
	 * @param string $idField Name of the primary-key field in each row.
	 * @param string $contentLabel Human-readable label for log messages (e.g. "page").
	 * @param callable $updateFn Callback: fn(int $id, array $ids): void
	 * @return void
	 */
	private function updateBodyContentIdsForRows(
		array $rows, string $idField, string $contentLabel, callable $updateFn
	): void {
		foreach ( $rows as $row ) {
			if ( !isset( $row[$idField] ) || !isset( $row['body_content_ids'] ) ) {
				continue;
			}

			$id = (int)$row[$idField];
			$bodyContentIds = json_decode( $row['body_content_ids'], true );

			if ( !empty( $bodyContentIds ) ) {
				continue;
			}

			$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $id );
			if ( !empty( $foundIds ) ) {
				$this->writeln(
					"Updated body_content_ids for $contentLabel ID $id with IDs: "
					. implode( ', ', $foundIds )
				);
				$updateFn( $id, $foundIds );
			}
		}
		$this->writeln( '... done' );
	}
}
