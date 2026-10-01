<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

class UpdateBlogPostCommentsTableWithWikiTitle extends ProcessorBase {

	private const NS_BLOG_NAME = 'Blog';
	private const NS_BLOG_TALK_NAME = 'Blog_Talk';

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getBlogPostComments() as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['blog_post_id'] ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			$blogPostId = (int)$comment['blog_post_id'];
			$wikiTitle = $this->workspaceDB->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
			if ( $wikiTitle === null || $wikiTitle === '' ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"No wiki title found for blog post comment ID $commentId (blog post ID $blogPostId)"
				);
				continue;
			}

			$blogNamespace = self::NS_BLOG_NAME . ':';
			if ( str_starts_with( $wikiTitle, $blogNamespace ) ) {
				$wikiTitle = self::NS_BLOG_TALK_NAME . ':' . substr( $wikiTitle, strlen( $blogNamespace ) );
			}

			$this->workspaceDB->updateBlogPostCommentWikiTitle( $commentId, $wikiTitle );
			$this->writeln( "Updated wiki title for blog post comment ID $commentId with title '$wikiTitle'" );
		}
	}
}
