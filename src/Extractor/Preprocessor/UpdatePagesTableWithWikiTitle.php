<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;

/**
 */
class UpdatePagesTableWithWikiTitle extends ProcessorBase {

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
		$titleBuilder = new TitleBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->workspaceDB->getMapSpaceIdToHomepageId(),
			$this->workspaceDB->getMapPageIdtoParentPageId(),
			$this->workspaceDB->getMapPageIdToConfluenceTitle(),
			$this->migrationConfig->getMainPageName()
		);

		$pages = $this->workspaceDB->getPages();
		$pageIdToWikiTitleMap = [];
		foreach ( $pages as $page ) {
			if (
				!isset( $page['page_id'] )
				|| !isset( $page['space_id'] )
				|| !isset( $page['confluence_title'] )
				|| !isset( $page['content_status'] )
				// historical versions
				|| $page['original_version_id'] !== -1
			) {
				continue;
			}

			// Skip pages that already have a wiki_title set (e.g. templates).
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
				continue;
			}

			// Create a wiki page title only for current page versions.
			// This is needed to avoid creating wiki titles for deleted pages or old page versions.
			if ( $page['content_status'] !== 'current' ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$spaceId = (int)$page['space_id'];
			$confluenceTitle = (string)$page['confluence_title'];

			$this->writeln(
				"Creating wiki title for page ID $pageId with confluence title '$confluenceTitle'"
			);

			try {
				$wikiTitle = $titleBuilder->buildTitle( $spaceId, $pageId, $confluenceTitle );
				$pageIdToWikiTitleMap[$pageId] = $wikiTitle;
			} catch ( Exception $ex ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Could not build wiki title for page $pageId: " . $ex->getMessage()
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
				"Updated wiki title for page ID $pageId with title: $wikiTitle"
			);
			$this->workspaceDB->updatePageWikiTitle( (int)$pageId, $wikiTitle );
		}
	}

}
