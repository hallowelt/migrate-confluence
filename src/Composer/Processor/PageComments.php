<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

/**
 * Generates Talk pages with cs-comments JSON slot for pages that have
 * Confluence page-level comments.
 */
class PageComments extends ProcessorBase {

	/**
	 * @param Builder $builder
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param ComposerSkipHelper $skipHelper
	 */
	public function __construct(
		protected Builder $builder,
		protected DBComposerDataLookup $dataLookup,
		protected Workspace $workspace,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
		protected ComposerDeploymentInfo $deploymentInfo,
		protected ComposerSkipHelper $skipHelper
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'page-talk';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addCommentPages();

		$this->writeOutputFile();
	}

	/**
	 * @return void
	 */
	private function addCommentPages(): void {
		$comments = $this->getComments();
		if ( empty( $comments ) ) {
			$this->output->writeln( "No comments found, skipping comment processing." );
			return;
		}

		$pageIdToCommentsMap = $this->getPageIdToCommentsMap( $comments );

		foreach ( $pageIdToCommentsMap as $pageId => $comments ) {
			$wikiTitle = $this->getWikiTitle( $pageId );
			$talkTitle = $this->getTalkTitle( $pageId );

			if ( $wikiTitle === null || $talkTitle === null ) {
				$this->output->writeln( "Warning: No title found for page ID $pageId, skipping comments." );
				continue;
			}

			if (
				$this->skipHelper->skipPage( $wikiTitle )
				|| $this->skipHelper->skipPage( $talkTitle )
			) {
				$this->output->writeln( "Skip page $talkTitle." );
				$this->deploymentInfo->addSkippedPage( $talkTitle );
				continue;
			}

			$commentsData = $this->buildCommentsData( $comments );

			if ( empty( $commentsData ) ) {
				continue;
			}

			$this->output->writeln( "Adding comments for Talk page '$talkTitle'..." );
			$this->appendTalkPageWithComments( $talkTitle, $commentsData );
		}
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	protected function getWikiTitle( int $pageId ): ?string {
		return $this->dataLookup->getWikiPageTitleFromPageId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	protected function getTalkTitle( int $pageId ): ?string {
		return $this->dataLookup->getWikiPageCommentTitleFromPageId( $pageId );
	}

	/**
	 * @return array
	 */
	protected function getComments(): array {
		$comments = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				$comments = array_merge(
					$comments,
					$this->dataLookup->getCommentsForPages( (int)$spaceId )
				);
			}
		} else {
			$comments = $this->dataLookup->getCommentsForPages();
		}
		return $comments;
	}

	/**
	 * @param array $comments
	 * @return array
	 */
	private function getPageIdToCommentsMap( array $comments ): array {
		$pageIdToCommentsMap = [];
		foreach ( $comments as $comment ) {
			$containerContentId = $comment['container_id'];
			$wikiTitle = $comment['wiki_title'];

			if ( $this->skipHelper->skipWikiTitle( $wikiTitle ) ) {
				$this->output->writeln( "Skip comments for page title $wikiTitle." );
				continue;
			}

			if ( $containerContentId === null ) {
				continue;
			}

			if ( $wikiTitle === null || $wikiTitle === '' ) {
				continue;
			}
			$this->output->writeln( "Processing comments for page title $wikiTitle ..." );

			if ( !isset( $pageIdToCommentsMap[$containerContentId] ) ) {
				$pageIdToCommentsMap[$containerContentId] = [];
			}
			$pageIdToCommentsMap[$containerContentId][] = $comment;
		}
		return $pageIdToCommentsMap;
	}

	/**
	 * @return array
	 */
	protected function getUserKeyToUsernameMap(): array {
		$userkeyToUsernameMap = [];
		$users = $this->dataLookup->getUsers();
		foreach ( $users as $user ) {
			$userKey = $user['user_key'];
			$username = $user['wiki_user_name'];
			$userkeyToUsernameMap[$userKey] = $username;
		}
		return $userkeyToUsernameMap;
	}

	/**
	 * @param array $comments
	 * @return array
	 */
	protected function buildCommentsData( array $comments ): array {
		$userKeyToUsernameMap = $this->getUserKeyToUsernameMap();

		$commentsData = [];
		$index = 1;
		foreach ( $comments as $comment ) {
			$commentId = $comment['comment_id'];
			$bodyContentIds = json_decode( $comment['body_content_ids'], true );

			if ( empty( $bodyContentIds ) ) {
				$this->output->writeln(
					"Warning: No converted content for comment $commentId, skipping."
				);
				continue;
			}

			$bodyContentId = $bodyContentIds[0];
			$wikitext = $this->workspace->getConvertedContent( $bodyContentId );
			if ( empty( $wikitext ) ) {
				$this->output->writeln(
					"Warning: No converted content for comment $commentId (body content $bodyContentId), skipping."
				);
				continue;
			}

			$creatorKey = $comment['user_key'];
			$username = isset( $userKeyToUsernameMap[$creatorKey] )
				? $userKeyToUsernameMap[$creatorKey]
				: $creatorKey;

			$commentsData[$index] = [
				'type' => 'comment',
				'author' => $username,
				'created' => $comment['created'],
				'modified' => $comment['modified'],
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
