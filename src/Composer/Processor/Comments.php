<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

/**
 * Generates Talk pages with cs-comments JSON slot for pages that have
 * Confluence page-level comments.
 */
class Comments extends ProcessorBase {

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'comments';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addBlogPages();

		$this->writeOutputFile();
	}

	/**
	 * @return void
	 */
	private function addBlogPages(): void {
		$comments = $this->dataLookup->getCommentsForPages();
		if ( empty( $comments ) ) {
			$this->output->writeln( "No comments found, skipping comment processing." );
			return;
		}

		$pageIdToCommentIds = [];
		$pageIdToTitleMap = [];
		foreach ( $comments as $comment ) {
			$commentId = $comment['comment_id'];
			$containerContentId = $comment['container_id'];
			$contentStatus = $comment['content_status'];
			$wikiTitle = $comment['wiki_title'];

			if ( $this->skipHelper->skipWikiTitle( $wikiTitle ) ) {
				$this->output->writeln( "Skip comments for page titel $wikiTitle." );
				continue;
			}
			$this->output->writeln( "Processing comments for page title $wikiTitle ..." );

			// Only handle page-level comments with content status 'current'
			if ( $containerContentId === null || $contentStatus !== 'current' ) {
				continue;
			}

			if ( $wikiTitle === null || $wikiTitle === '' ) {
				continue;
			}

			if ( !isset( $pageIdToCommentIds[$containerContentId] ) ) {
				$pageIdToCommentIds[$containerContentId] = [];
			}
			$pageIdToCommentIds[$containerContentId][] = $commentId;
			$pageIdToTitleMap[$containerContentId] = $wikiTitle;
		}

		$commentIdToMetadata = [];
		foreach ( $comments as $comment ) {
			$commentId = $comment['comment_id'];
			$pageId = $comment['container_id'];
			$bodyContentIds = json_decode( $comment['body_content_ids'], true );
			$bodyContentId = $bodyContentIds[0] ?? null;
			$creatorKey = $comment['user_key'];
			$created = $comment['created'];
			$modified = $comment['modified'];

			if ( $bodyContentId === null ) {
				continue;
			}

			$commentIdToMetadata[$commentId] = [
				'page_id' => $pageId,
				'body_content_id' => $bodyContentId,
				'creator_key' => $creatorKey,
				'created' => $created,
				'modified' => $modified,
			];
		}

		$userkeyToUsernameMap = [];
		$users = $this->dataLookup->getUsers();
		foreach ( $users as $user ) {
			$userKey = $user['user_key'];
			$username = $user['wiki_user_name'];
			$userkeyToUsernameMap[$userKey] = $username;
		}

		if ( empty( $pageIdToCommentIds ) ) {
			return;
		}

		foreach ( $pageIdToCommentIds as $pageId => $commentIds ) {
			if ( !isset( $pageIdToTitleMap[$pageId] ) ) {
				$this->output->writeln( "Warning: No title found for page ID $pageId, skipping comments." );
				continue;
			}
			$pageTitle = $pageIdToTitleMap[$pageId];
			$talkTitle = $this->buildTalkTitle( $pageTitle );

			$commentsData = $this->buildCommentsData(
				$commentIds, $commentIdToMetadata, $userkeyToUsernameMap
			);

			if ( empty( $commentsData ) ) {
				continue;
			}

			$this->output->writeln( "Adding comments for Talk page '$talkTitle'..." );
			$this->appendTalkPageWithComments( $talkTitle, $commentsData );
		}
	}

	/**
	 * Build the correct Talk page title respecting namespaces:
	 * "NS:Page" → "NS_Talk:Page", plain "Page" → "Talk:Page"
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	private function buildTalkTitle( string $pageTitle ): string {
		$prefix = $this->migrationConfig->getNsTalkPrefix();
		if ( strpos( $pageTitle, ':' ) !== false ) {
			[ $ns, $titlePart ] = explode( ':', $pageTitle, 2 );
			return $ns . '_' . "$prefix:$titlePart";
		}
		return $prefix . ':' . $pageTitle;
	}

	/**
	 * @param array $commentIds
	 * @param array $commentIdToMetadata
	 * @param array $userkeyToUsernameMap
	 * @return array
	 */
	private function buildCommentsData(
		array $commentIds, array $commentIdToMetadata, array $userkeyToUsernameMap
	): array {
		$commentsData = [];
		$index = 1;
		foreach ( $commentIds as $commentId ) {
			if ( !isset( $commentIdToMetadata[$commentId] ) ) {
				continue;
			}
			$metadata = $commentIdToMetadata[$commentId];
			$bodyContentId = $metadata['body_content_id'];

			$wikitext = $this->workspace->getConvertedContent( $bodyContentId );
			if ( empty( $wikitext ) ) {
				$this->output->writeln(
					"Warning: No converted content for comment $commentId (body content $bodyContentId), skipping."
				);
				continue;
			}

			$creatorKey = $metadata['creator_key'];
			$username = isset( $userkeyToUsernameMap[$creatorKey] )
				? $userkeyToUsernameMap[$creatorKey]
				: $creatorKey;

			$commentsData[$index] = [
				'type' => 'comment',
				'author' => $username,
				'created' => $metadata['created'],
				'modified' => $metadata['modified'],
				'title' => '',
				'block' => null,
				'wikitext' => trim( $wikitext ),
			];
			$index++;
		}
		return $commentsData;
	}

	/**
	 * @param string $talkTitle
	 * @param array $commentsData
	 * @return void
	 */
	private function appendTalkPageWithComments(
		string $talkTitle, array $commentsData
	): void {
		// JSON_HEX_TAG | JSON_HEX_AMP: hex-escape <, >, & so the JSON contains no XML-special
		// characters and the serialiser never needs to entity-encode them.
		$slotText = json_encode( $commentsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );

		$this->addRevision(
			$talkTitle,
			'',
			'',
			'',
			'wikitext',
			'text/x-wiki',
			[
				'role' => 'cs-comments',
				'model' => 'json',
				'format' => 'application/json',
				'text' => $slotText
			]
		);
	}

}
