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
		// Update pages table
		$this->updatePagesTable();

		// Update blog_posts table
		$this->updateBlogPostsTable();

		// Update comments table
		$this->updateCommentsTable();

		// Update spaces_descriptions table
		$this->updateSpaceDescriptionsTable();
	}

	/**
	 * @return void
	 */
	private function updatePagesTable(): void {
		$pages = $this->workspaceDB->getPages();
		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'] ) || !isset( $page['body_content_ids'] ) ) {
				continue;
			}

			$bodyContentIds = json_decode( $page['body_content_ids'], true );
			if ( !empty( $bodyContentIds ) ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $pageId );
			if ( empty( $foundIds ) ) {
				continue;
			}

			$this->workspaceDB->updatePageBodyContentIds( $pageId, $foundIds );

			$this->writeln(
				"Updated body_content_ids for page ID $pageId with IDs: " . implode( ', ', $foundIds )
			);
		}
		$this->writeln( "... done" );
	}

	/**
	 * @return void
	 */
	private function updateBlogPostsTable(): void {
		$blogPosts = $this->workspaceDB->getBlogPosts();
		foreach ( $blogPosts as $blogPost ) {
			if ( !isset( $blogPost['page_id'] ) || !isset( $blogPost['body_content_ids'] ) ) {
				continue;
			}

			$bodyContentIds = json_decode( $blogPost['body_content_ids'], true );
			if ( !empty( $bodyContentIds ) ) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];
			$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $pageId );
			if ( empty( $foundIds ) ) {
				continue;
			}

			$this->workspaceDB->updateBlogPostBodyContentIds( $pageId, $foundIds );

			$this->writeln(
				"Updated body_content_ids for blog post ID $pageId with IDs: " . implode( ', ', $foundIds )
			);
		}
	}

	/**
	 * @return void
	 */
	private function updateCommentsTable(): void {
		$comments = $this->workspaceDB->getComments();
		foreach ( $comments as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['body_content_ids'] ) ) {
				continue;
			}

			$bodyContentIds = json_decode( $comment['body_content_ids'], true );
			if ( !empty( $bodyContentIds ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $commentId );

			if ( empty( $foundIds ) ) {
				continue;
			}

			$this->workspaceDB->updateCommentBodyContentIds( $commentId, $foundIds );

			$this->writeln(
				"Updated body_content_ids for comment ID $commentId with IDs: " . implode( ', ', $foundIds )
			);
		}
	}

	/**
	 * @return void
	 */
	private function updateSpaceDescriptionsTable(): void {
		$spaceDescriptions = $this->workspaceDB->getSpaceDescriptions();
		foreach ( $spaceDescriptions as $spaceDesc ) {
			if ( !isset( $spaceDesc['space_description_id'] ) || !isset( $spaceDesc['body_content_ids'] ) ) {
				continue;
			}

			$bodyContentIds = json_decode( $spaceDesc['body_content_ids'], true );
			if ( !empty( $bodyContentIds ) ) {
				continue;
			}

			$spaceDescriptionId = (int)$spaceDesc['space_description_id'];
			$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $spaceDescriptionId );
			if ( empty( $foundIds ) ) {
				continue;
			}

			$this->workspaceDB->updateSpaceDescriptionBodyContentIds( $spaceDescriptionId, $foundIds );

			$this->writeln(
				"Updated body_content_ids for space description ID $spaceDescriptionId with IDs: " .
				implode( ', ', $foundIds )
			);
		}
	}
}
