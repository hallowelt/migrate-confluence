<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use DOMDocument;

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
		$pageIdToCommentIds = $this->buckets->getBucketData( 'global-page-id-to-comment-ids-map' );
		$commentIdToMetadata = $this->buckets->getBucketData( 'global-comment-id-to-metadata-map' );
		$pageIdToTitleMap = $this->buckets->getBucketData( 'global-page-id-to-title-map' );
		$userkeyToUsernameMap = $this->buckets->getBucketData( 'global-userkey-to-username-map' );

		if ( empty( $pageIdToCommentIds ) ) {
			return;
		}

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$root = $dom->createElement( 'mediawiki' );
		$dom->appendChild( $root );

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
			$this->appendTalkPageWithComments( $dom, $root, $talkTitle, $commentsData );
		}

		if ( !$root->hasChildNodes() ) {
			return;
		}

		$destFile = $this->dest . '/result/' . $this->getOutputName() . '.xml';
		$dom->save( $destFile );
		$this->output->writeln( "Comments written to '$destFile'." );
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
				'created' => $this->toMwTimestamp( $metadata['created'] ),
				'modified' => $this->toMwTimestamp( $metadata['modified'] ),
				'title' => '',
				'block' => null,
				'wikitext' => trim( $wikitext ),
			];
			$index++;
		}
		return $commentsData;
	}

	/**
	 * @param DOMDocument $dom
	 * @param \DOMElement $root
	 * @param string $talkTitle
	 * @param array $commentsData
	 * @return void
	 */
	private function appendTalkPageWithComments(
		DOMDocument $dom, \DOMElement $root, string $talkTitle, array $commentsData
	): void {
		$pageEl = $dom->createElement( 'page' );

		$titleEl = $dom->createElement( 'title' );
		$titleEl->appendChild( $dom->createTextNode( $talkTitle ) );
		$pageEl->appendChild( $titleEl );

		$revisionEl = $dom->createElement( 'revision' );

		// Main slot: empty wikitext
		$modelEl = $dom->createElement( 'model' );
		$modelEl->appendChild( $dom->createTextNode( 'wikitext' ) );
		$revisionEl->appendChild( $modelEl );

		$formatEl = $dom->createElement( 'format' );
		$formatEl->appendChild( $dom->createTextNode( 'text/x-wiki' ) );
		$revisionEl->appendChild( $formatEl );

		$textEl = $dom->createElement( 'text' );
		$textEl->setAttribute( 'bytes', '0' );
		$textEl->setAttribute( 'xml:space', 'preserve' );
		$revisionEl->appendChild( $textEl );

		// cs-comments content slot
		$contentEl = $dom->createElement( 'content' );

		$roleEl = $dom->createElement( 'role' );
		$roleEl->appendChild( $dom->createTextNode( 'cs-comments' ) );
		$contentEl->appendChild( $roleEl );

		$slotModelEl = $dom->createElement( 'model' );
		$slotModelEl->appendChild( $dom->createTextNode( 'json' ) );
		$contentEl->appendChild( $slotModelEl );

		$slotFormatEl = $dom->createElement( 'format' );
		$slotFormatEl->appendChild( $dom->createTextNode( 'application/json' ) );
		$contentEl->appendChild( $slotFormatEl );

		$slotTextEl = $dom->createElement( 'text' );
		$slotTextEl->setAttribute( 'xml:space', 'preserve' );
		// JSON_HEX_TAG | JSON_HEX_AMP: hex-escape <, >, & so the JSON contains no XML-special
		// characters and the serialiser never needs to entity-encode them.
		$slotTextEl->appendChild( $dom->createTextNode(
			json_encode( $commentsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
		) );
		$contentEl->appendChild( $slotTextEl );

		$revisionEl->appendChild( $contentEl );
		$pageEl->appendChild( $revisionEl );
		$root->appendChild( $pageEl );
	}

	/**
	 * Converts a Confluence datetime string (e.g. "2026-02-12 17:09:43.563")
	 * to a MediaWiki timestamp (e.g. "20260212170943").
	 *
	 * @param string $confluenceDate
	 * @return string
	 */
	private function toMwTimestamp( string $confluenceDate ): string {
		$time = strtotime( $confluenceDate );
		if ( $time === false ) {
			return '';
		}
		return date( 'YmdHis', $time );
	}
}
