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
		foreach ( $this->workspaceDB->getCurrentBlogPosts() as $blogPost ) {
			if ( isset( $blogPost['page_id'] ) ) {
				$currentContentIds[] = (int)$blogPost['page_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

}
