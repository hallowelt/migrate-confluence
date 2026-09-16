<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractCommentsBodyContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->extractPageCommentsBodyContents();
		$this->extractPageBlogPostBodyContents();
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getCurrentComments() as $comment ) {
			if ( !isset( $comment['comment_id'] )
				|| !isset( $comment['content_class'] )
			) {
				continue;
			}

			// Comments composer handles both page-level and blog post comments.
			if ( !in_array( (string)$comment['content_class'], [ 'Page', 'BlogPost' ], true )
			) {
				continue;
			}

			$currentContentIds[] = (int)$comment['comment_id'];
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @return void
	 */
	private function extractPageCommentsBodyContents(): void {
	}

	/**
	 * @return void
	 */
	private function extractPageBlogPostBodyContents(): void {
		$this->workspaceDB->getPageComments();
	}
}
