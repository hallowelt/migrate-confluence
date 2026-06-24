<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractBlogPostsMetaData extends ExtractPagesMetaData {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			if ( !isset( $blogPost['page_id'] ) || !isset( $blogPost['original_version_id'] ) ) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];
			$originalVersionId = (int)$blogPost['original_version_id'];
			$labellings = $blogPost['collection']['labellings'] ?? [];

			if ( $originalVersionId !== -1 ) {
				continue;
			}

			$categories = $this->getCategoryMeta( $labellings );

			if ( empty( $categories ) ) {
				continue;
			}

			$this->workspaceDB->addBlogPostMeta(
				$pageId,
				[
					'categories' => $categories
				]
			);

			$this->dbLog->addLogEntry(
				'info',
				'extract',
				__METHOD__,
				"Add blog post meta for page {$blogPost['wiki_title']}"
			);
		}
	}

}
