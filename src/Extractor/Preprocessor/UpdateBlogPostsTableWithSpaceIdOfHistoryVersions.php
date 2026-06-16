<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

class UpdateBlogPostsTableWithSpaceIdOfHistoryVersions extends UpdateTableWithSpaceIdOfHistoryVersionsBase {

	/** @inheritDoc */
	protected function getRows(): array {
		return $this->workspaceDB->getBlogPosts();
	}

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'blog post';
	}

	/** @inheritDoc */
	protected function updateSpaceId( int $pageId, int $spaceId ): void {
		$this->workspaceDB->updateBlogPostSpaceId( $pageId, $spaceId );
	}
}
