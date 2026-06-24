<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Space id of historical versions can be -1 but we need the space id for converter
 * Use space id from original version and update history versions
 */
class UpdateBlogPostsTableWithSpaceIdOfHistoryVersions extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$pageIdToSpaceIdMap = [];
		$pendingUpdates = [];

		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			if ( !isset( $blogPost['page_id'] ) || !array_key_exists( 'space_id', $blogPost ) ) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];

			if ( $blogPost['space_id'] !== null ) {
				$pageIdToSpaceIdMap[$pageId] = (int)$blogPost['space_id'];
			} elseif ( isset( $blogPost['original_version_id'] )
				&& (int)$blogPost['original_version_id'] !== -1
			) {
				$pendingUpdates[$pageId] = (int)$blogPost['original_version_id'];
			}
		}

		foreach ( $pendingUpdates as $pageId => $originalVersionId ) {
			if ( !isset( $pageIdToSpaceIdMap[$originalVersionId] ) ) {
				continue;
			}

			$originalSpaceId = $pageIdToSpaceIdMap[$originalVersionId];
			$this->workspaceDB->updateBlogPostSpaceId( $pageId, $originalSpaceId );
			$this->writeln(
				"Updated space_id for historical blog post ID $pageId with space_id: $originalSpaceId"
			);
		}
	}

}
