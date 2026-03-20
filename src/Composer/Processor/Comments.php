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
		$pageIdToCommentIds = $this->buckets->getBucketData( 'global-page-id-to-comment-ids-map' );
		$commentIdToMetadata = $this->buckets->getBucketData( 'global-comment-id-to-metadata-map' );
		$pageIdToTitleMap = $this->buckets->getBucketData( 'global-page-id-to-title-map' );
		$userkeyToUsernameMap = $this->buckets->getBucketData( 'global-userkey-to-username-map' );

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
		if ( strpos( $pageTitle, ':' ) !== false ) {
			[ $ns, $titlePart ] = explode( ':', $pageTitle, 2 );
			return $ns . '_Talk:' . $titlePart;
		}
		return 'Talk:' . $pageTitle;
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
			if ( $wikitext === false ) {
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
