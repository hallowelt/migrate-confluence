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
class Comments extends ContentProcessorBase {

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
		return 'comments';
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
		$comments = $this->collectBySpaceIdsAppend(
			fn ( int $spaceId ): array => array_merge(
				$this->dataLookup->getCommentsForPages( $spaceId ),
				$this->dataLookup->getCommentsForBlogPosts( $spaceId )
			),
			fn (): array => array_merge(
				$this->dataLookup->getCommentsForPages(),
				$this->dataLookup->getCommentsForBlogPosts()
			)
		);

		if ( empty( $comments ) ) {
			$this->output->writeln( "No comments found, skipping comment processing." );
			return;
		}

		$pageIdToCommentIds = [];
		$pageIdToTitleMap = [];
		foreach ( $comments as $comment ) {
			$commentId = $comment['comment_id'];
			$containerContentId = $comment['container_id'];
			$wikiTitle = $comment['wiki_title'];

			if ( $this->skipHelper->skipWikiTitle( $wikiTitle ) ) {
				$this->output->writeln( "Skip comments for page title $wikiTitle." );
				continue;
			}
			$this->output->writeln( "Processing comments for page title $wikiTitle ..." );

			// Only handle page-level comments with content status 'current'
			if ( $containerContentId === null ) {
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

		$userkeyToUsernameMap = $this->buildUserkeyToUsernameMap( $this->dataLookup );

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
			if ( $this->skipHelper->skipPage( $pageTitle ) ) {
				$this->output->writeln( "Skip page $talkTitle." );
				$this->deploymentInfo->addSkippedPage( $talkTitle );
				continue;
			}

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
