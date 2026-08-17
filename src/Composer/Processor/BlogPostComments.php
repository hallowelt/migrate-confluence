<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

/**
 * Generates Blog_Talk pages with cs-comments JSON slot for blog posts that have
 * Confluence blog-post-level comments.
 */
class BlogPostComments extends PageComments {

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'blog-talk';
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	protected function getWikiTitle( int $pageId ): ?string {
		return $this->reader->getWikiBlogPostTitleFromBlogPostId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	protected function getTalkTitle( int $pageId ): ?string {
		return $this->reader->getWikiBlogPostCommentsFromBlogPostId( $pageId );
	}

	/**
	 * @return array
	 */
	protected function getComments(): array {
		$comments = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				$comments = array_merge(
					$comments,
					$this->reader->getCommentsForBlogPosts( (int)$spaceId )
				);
			}
		} else {
			$comments = $this->reader->getCommentsForBlogPosts();
		}

		return $comments;
	}

	/**
	 * @param string $wikiTitle
	 * @param string $talkTitle
	 * @return bool
	 */
	protected function skip( string $wikiTitle, string $talkTitle ): bool {
		if ( $this->skipHelper->skipBlogPost( $wikiTitle )
			|| $this->skipTalkTitleByConfiguration( $talkTitle ) ) {
			return true;
		}
		return false;
	}
}
