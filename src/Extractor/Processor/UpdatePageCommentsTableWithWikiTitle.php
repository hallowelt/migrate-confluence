<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

class UpdatePageCommentsTableWithWikiTitle extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getPageComments() as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['page_id'] ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			$pageId = (int)$comment['page_id'];
			$wikiTitle = $this->workspaceDB->getWikiPageTitleFromPageId( $pageId );
			if ( $wikiTitle === null || $wikiTitle === '' ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"No wiki title found for page comment ID $commentId (page ID $pageId)"
				);
				continue;
			}

			$colonPos = strpos( $wikiTitle, ':' );
			if ( $colonPos !== false ) {
				$namespace = substr( $wikiTitle, 0, $colonPos );
				$title = substr( $wikiTitle, $colonPos + 1 );
				$talkTitle = $namespace . '_Talk:' . $title;
			} else {
				$talkTitle = 'Talk:' . $wikiTitle;
			}

			$this->workspaceDB->updatePageCommentWikiTitle( $commentId, $talkTitle );
			$this->writeln( "Updated wiki title for page comment ID $commentId with title '$talkTitle'" );
		}
	}
}
