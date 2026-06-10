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
			$categories = [];

			if ( isset( $blogPost['page_id'] ) && isset( $blogPost['original_version_id'] )
				&& (int)$blogPost['original_version_id'] === -1
			) {
				if ( !isset( $blogPost['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $blogPost['collection']['labellings'];
				foreach ( $labellings as $labellingId ) {
					$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
					if ( $labelling === null || !isset( $labelling['label_id'] ) ) {
						continue;
					}
					$labelId = (int)$labelling['label_id'];
					$label = $this->workspaceDB->getLabelById( $labelId );
					if ( $label === null || !isset( $label['name'] ) ) {
						continue;
					}

					$categories[] = $label['name'];
				}

				$this->workspaceDB->addBlogPostMeta(
					(int)$blogPost['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add blog post meta for page {$blogPost['wiki_title']}"
				);
			}
		}
	}

}
