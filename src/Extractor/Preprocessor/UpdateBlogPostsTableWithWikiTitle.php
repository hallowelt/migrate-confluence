<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;

/**
 */
class UpdateBlogPostsTableWithWikiTitle extends ProcessorBase {

	private const NS_BLOG_NAME = 'Blog';

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, DBLog $dbLog, MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );

		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$spaceIdToSpaceKeyMap = $this->workspaceDB->getMapSpaceIdToKey();
		$blogPosts = $this->workspaceDB->getBlogPosts();
		$pageIdToWikiTitleMap = [];

		foreach ( $blogPosts as $blogPost ) {
			if (
				!isset( $blogPost['page_id'] )
				|| !isset( $blogPost['space_id'] )
				|| !isset( $blogPost['confluence_title'] )
				|| !isset( $blogPost['content_status'] )
				// historical versions
				|| $blogPost['original_version_id'] !== -1
			) {
				continue;
			}

			if ( $blogPost['content_status'] !== 'current' ) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];
			$spaceId = (int)$blogPost['space_id'];
			$confluenceTitle = (string)$blogPost['confluence_title'];

			if ( !isset( $spaceIdToSpaceKeyMap[$spaceId] ) ) {
				continue;
			}

			$this->writeln(
				"Creating wiki title for blog post ID $pageId with confluence title '$confluenceTitle'"
			);

			$spaceKey = '';
			if ( isset( $spaceIdToSpaceKeyMap[$spaceId] ) ) {
				$spaceKey = $spaceIdToSpaceKeyMap[$spaceId] . '/';
			}
			$blogName = self::NS_BLOG_NAME;
			$titleBuilder = new TitleBuilder( [ $spaceId => "$blogName:$spaceKey" ], [], [], [] );

			try {
				$wikiTitle = $titleBuilder->buildTitle( $spaceId, $pageId, $confluenceTitle );
				$pageIdToWikiTitleMap[$pageId] = $wikiTitle;
			} catch ( Exception $ex ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					'Could not build wiki title for blog post ' . $pageId . ': ' . $ex->getMessage()
				);
			}
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $pageIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedPageIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $pageIdToWikiTitleMap );

		foreach ( $compressedPageIdToWikiTitleMap as $pageId => $wikiTitle ) {
			$this->writeln(
				"Updated wiki title for blog post ID $pageId with title: $wikiTitle"
			);
			$this->workspaceDB->updateBlogPostWikiTitle( (int)$pageId, $wikiTitle );
		}
	}

}
