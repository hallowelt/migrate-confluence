<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;

abstract class ContentProcessorBase extends ProcessorBase {

	/**
	 * Merge array chunks per configured space IDs while preserving keys from later chunks.
	 *
	 * @param callable $bySpaceId function( int $spaceId ): array
	 * @param callable $allSpaces function(): array
	 * @return array
	 */
	protected function collectBySpaceIdsReplaceByKey( callable $bySpaceId, callable $allSpaces ): array {
		if ( !is_array( $this->currentSpaceIds ) ) {
			return $allSpaces();
		}

		$result = [];
		foreach ( $this->currentSpaceIds as $spaceId ) {
			$result = array_replace( $result, $bySpaceId( (int)$spaceId ) );
		}

		return $result;
	}

	/**
	 * Merge array chunks per configured space IDs by appending numeric entries.
	 *
	 * @param callable $bySpaceId function( int $spaceId ): array
	 * @param callable $allSpaces function(): array
	 * @return array
	 */
	protected function collectBySpaceIdsAppend( callable $bySpaceId, callable $allSpaces ): array {
		if ( !is_array( $this->currentSpaceIds ) ) {
			return $allSpaces();
		}

		$result = [];
		foreach ( $this->currentSpaceIds as $spaceId ) {
			$result = array_merge( $result, $bySpaceId( (int)$spaceId ) );
		}

		return $result;
	}

	/**
	 * @param Workspace $workspace
	 * @param string $contentIdsJson
	 * @param string $contentLabel
	 * @param string $idPrefix
	 * @param bool $logEachId
	 * @return string
	 */
	protected function buildConvertedContentFromIdsJson(
		Workspace $workspace,
		string $contentIdsJson,
		string $contentLabel = 'body content',
		string $idPrefix = '',
		bool $logEachId = false
	): string {
		$contentIds = json_decode( $contentIdsJson, true );
		if ( !is_array( $contentIds ) ) {
			return '';
		}

		$content = '';
		foreach ( $contentIds as $contentId ) {
			if ( empty( $contentId ) ) {
				continue;
			}

			if ( $logEachId ) {
				$this->output->writeln( "Getting '$contentId' $contentLabel..." );
			}

			$convertedId = $idPrefix . $contentId;
			$content .= $workspace->getConvertedContent( $convertedId ) . "\n";
		}

		return $content;
	}

	/**
	 * @param string $contentIdsJson
	 * @return bool
	 */
	protected function hasValidContentIdsJson( string $contentIdsJson ): bool {
		return is_array( json_decode( $contentIdsJson, true ) );
	}

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @return array
	 */
	protected function buildUserkeyToUsernameMap( DBComposerDataLookup $dataLookup ): array {
		$userkeyToUsernameMap = [];
		$users = $dataLookup->getUsers();
		foreach ( $users as $user ) {
			$userKey = (string)( $user['user_key'] ?? '' );
			$wikiUsername = (string)( $user['wiki_user_name'] ?? '' );
			if ( $userKey === '' ) {
				continue;
			}
			$userkeyToUsernameMap[$userKey] = $wikiUsername;
		}

		return $userkeyToUsernameMap;
	}

	/**
	 * Build the correct Talk page title respecting namespaces:
	 * "NS:Page" -> "NS_Talk:Page", plain "Page" -> "Talk:Page"
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	protected function buildTalkTitle( string $pageTitle ): string {
		$prefix = $this->migrationConfig->getNsTalkPrefix();
		if ( strpos( $pageTitle, ':' ) !== false ) {
			[ $ns, $titlePart ] = explode( ':', $pageTitle, 2 );
			return $ns . '_' . "$prefix:$titlePart";
		}
		return $prefix . ':' . $pageTitle;
	}

	/**
	 * Add space description to homepage.
	 *
	 * @param int $pageId
	 * @param int $homepageId
	 * @param string $pageRevisionTimestamp
	 * @param array $spaceDescriptionRevisions
	 *
	 * @return string
	 */
	protected function addSpaceDescriptionToMainPage(
		int $pageId,
		int $homepageId,
		string $pageRevisionTimestamp,
		array $spaceDescriptionRevisions
	): string {
		if ( $pageId !== $homepageId ) {
			return '';
		}

		foreach ( $spaceDescriptionRevisions as $spaceDescriptionRevision ) {
			if ( !isset( $spaceDescriptionRevision['revision_timestamp'] ) ) {
				continue;
			}

			$spaceDescriptionTimestamp = (string)$spaceDescriptionRevision['revision_timestamp'];
			if ( $spaceDescriptionTimestamp > $pageRevisionTimestamp ) {
				continue;
			}

			$description = $this->buildConvertedContentFromIdsJson(
				$this->resolveWorkspace(),
				(string)( $spaceDescriptionRevision['body_content_ids'] ?? '' )
			);

			if ( $description !== '' ) {
				return $this->wrapSpaceDescription( $description );
			}
		}

		return '';
	}

	/**
	 * Resolve workspace from child processor instance.
	 *
	 * @return Workspace
	 */
	private function resolveWorkspace(): Workspace {
		/** @var Workspace $workspace */
		$workspace = $this->{'workspace'};
		return $workspace;
	}

	/**
	 * @param string $description
	 * @return string
	 */
	protected function wrapSpaceDescription( string $description ): string {
		$strippedDescription = trim( preg_replace( '/<!-- From bodyContent .*?-->/s', '', (string)$description ) );
		if ( $strippedDescription === '' ) {
			return '';
		}
		return '<div class="space-description">' . $description . '</div>';
	}
}
