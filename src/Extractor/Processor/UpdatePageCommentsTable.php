<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 */
class UpdatePageCommentsTable extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$comments = $this->workspaceDB->getCommentsForPages();

		foreach ( $comments as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['container_id'] ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			$pageId = (int)$comment['container_id'];
			$wikiTitle = (string)( $comment['wiki_title'] ?? '' );

			if ( $wikiTitle === '' ) {
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

			$this->workspaceDB->addPageComment( $commentId, $pageId, $talkTitle );

			$this->writeln(
				"Added page comment ID $commentId for page ID $pageId with title '$talkTitle'"
			);
		}
	}
}
