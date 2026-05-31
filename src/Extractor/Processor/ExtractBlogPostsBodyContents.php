<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractBlogPostsBodyContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			if ( isset( $blogPost['page_id'] ) && isset( $blogPost['content_status'] )
				&& strtolower( (string)$blogPost['content_status'] ) === 'current'
			) {
				$currentContentIds[] = (int)$blogPost['page_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

}