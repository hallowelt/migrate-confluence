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
		foreach ( $this->workspaceDB->getComments() as $comment ) {
			if ( !isset( $comment['comment_id'] )
				|| !isset( $comment['content_class'] )
			) {
				continue;
			}

			// Comments composer currently handles page-level comments only.
			if ( (string)$comment['content_class'] !== 'Page' ) {
				continue;
			}

			$currentContentIds[] = (int)$comment['comment_id'];
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

}
