<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\WikiTitleUniquifier;
use RuntimeException;

class UpdatePageCommentsTableWithWikiTitle extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$commentIdToWikiTitleMap = [];
		$commentIdToSpaceIdMap = [];
		foreach ( $this->workspaceDB->getPageComments() as $comment ) {
			if ( !isset( $comment['comment_id'] ) || !isset( $comment['page_id'] ) ) {
				continue;
			}

			$commentId = (int)$comment['comment_id'];
			if ( isset( $comment['wiki_title'] ) && $comment['wiki_title'] !== '' ) {
				continue;
			}

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

			$commentIdToWikiTitleMap[$commentId] = $talkTitle;
			$commentIdToSpaceIdMap[$commentId] = $this->workspaceDB->getSpaceIdForPageId( $pageId );
		}

		$spaceIdToWikiGroup = $this->workspaceDB->getSpaceIdToWikiGroupMap();
		$commentIdToWikiTitleMap = WikiTitleUniquifier::makeUniquePerWiki(
			$commentIdToWikiTitleMap,
			$commentIdToSpaceIdMap,
			$spaceIdToWikiGroup,
			fn ( array $spaceIds ) => $this->workspaceDB->getPageCommentWikiTitles( $spaceIds )
		);

		foreach ( $commentIdToWikiTitleMap as $commentId => $talkTitle ) {
			if ( !$this->workspaceDB->updatePageCommentWikiTitle( (int)$commentId, $talkTitle ) ) {
				$message = "Could not persist wiki title for page comment ID $commentId";
				$this->dbLog->addLogEntry( 'error', 'extract', __CLASS__, $message );
				throw new RuntimeException( $message );
			}
			$this->writeln( "Updated wiki title for page comment ID $commentId with title '$talkTitle'" );
		}
	}
}
