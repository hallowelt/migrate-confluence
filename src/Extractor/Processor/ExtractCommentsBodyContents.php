<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractCommentsBodyContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
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

}
