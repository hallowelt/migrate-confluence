<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;

/**
 */
class ExtractBlogPostsMetaData extends ExtractPagesMetaData {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getCurrentBlogPosts() as $blogPost ) {
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

			$this->writer->addBlogPostMeta(
				$pageId,
				[
					'categories' => $categories
				]
			);

			Log::to( 'db' )->info(
				"Add blog post meta for page {$blogPost['wiki_title']}"
			);
		}
	}

}
