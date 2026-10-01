<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Utility\CommentsHelper;

/**
 */
class ExtractCommentsBodyContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
		$commentsHelper = new CommentsHelper( $this->workspaceDB );

		// Use each comment's own (fallback-resolved) body_content_ids instead of reverse-looking
		// them up via body_contents.content_id, which is not always kept in sync for comments.
		// CommentsHelper already excludes inline comments, keeping only page/blog-post comments.
		$bodyContentIds = [];
		foreach ( $commentsHelper->getPageComments() as $comment ) {
			$bodyContentIds = array_merge( $bodyContentIds, $comment->getBodyContentIds() );

		}

		foreach ( $commentsHelper->getBlogPostComments() as $comment ) {
			$bodyContentIds = array_merge( $bodyContentIds, $comment->getBodyContentIds() );
		}

		$this->extractBodyContentIds( $bodyContentIds );
	}
}
