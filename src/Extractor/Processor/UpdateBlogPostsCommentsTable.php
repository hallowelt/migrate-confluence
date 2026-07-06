<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 */
class UpdateBlogPostsCommentsTable extends ProcessorBase {

	private const NS_BLOG_NAME = 'Blog';
	private const NS_BLOG_TALK_NAME = 'Blog_Talk';

	/**
	 * @return void
	 */
	public function execute(): void {
		$comments = $this->workspaceDB->getCommentsForBlogPosts();

		foreach ( $comments as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['container_id'] ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			$blogPostId = (int)$comment['container_id'];
			$wikiTitle = (string)( $comment['wiki_title'] ?? '' );

			if ( $wikiTitle === '' ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"No wiki title found for blog post comment ID $commentId (blog post ID $blogPostId)"
				);
				continue;
			}

			$blogNsPrefix = self::NS_BLOG_NAME . ':';
			if ( str_starts_with( $wikiTitle, $blogNsPrefix ) ) {
				$wikiTitle = self::NS_BLOG_TALK_NAME . ':' . substr( $wikiTitle, strlen( $blogNsPrefix ) );
			}

			$this->workspaceDB->addBlogPostComment( $commentId, $blogPostId, $wikiTitle );

			$this->writeln(
				"Added blog post comment ID $commentId for blog post ID $blogPostId with title '$wikiTitle'"
			);
		}
	}

}
