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
		$blogPosts = $this->workspaceDB->getBlogPosts();

		foreach ( $blogPosts as $blogPost ) {
			if ( !isset( $blogPost['page_id'] ) || !array_key_exists( 'space_id', $blogPost ) ) {
				continue;
			}

			if ( $blogPost['space_id'] === null ) {
				continue;
			}

			$pageIdToSpaceIdMap[(int)$blogPost['page_id']] = (int)$blogPost['space_id'];
		}

		foreach ( $blogPosts as $blogPost ) {
			if ( !isset( $blogPost['page_id'] )
				|| !array_key_exists( 'space_id', $blogPost )
				|| !isset( $blogPost['original_version_id'] )
			) {
				continue;
			}

			$originalVersionId = (int)$blogPost['original_version_id'];
			if ( $originalVersionId === -1 ) {
				continue;
			}

			if ( $blogPost['space_id'] !== null ) {
				continue;
			}
			$pageId = (int)$blogPost['page_id'];

			if ( !isset( $pageIdToSpaceIdMap[$originalVersionId] ) ) {
				continue;
			}

			$originalSpaceId = (int)$pageIdToSpaceIdMap[$originalVersionId];

			$this->workspaceDB->updateBlogPostSpaceId( $pageId, $originalSpaceId );
			$this->writeln(
				"Updated space_id for historical blog post ID $pageId with space_id: $originalSpaceId"
			);
		}
	}

}
