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
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;

/**
 */
class UpdatePagesTableWithWikiTitle extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
		private MigrationConfig $migrationConfig,
		private WikiConfig $wikiConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->updateWikiTitles();
		$this->checkWikiTitles();
	}

	/**
	 * @return void
	 */
	private function updateWikiTitles(): void {
		$spaceIdPrefixMap = $this->getSpaceIdPrefixMap();
		$titleBuilder = new TitleBuilder(
			$spaceIdPrefixMap,
			$this->workspaceDB->getMapSpaceIdToHomepageId(),
			$this->workspaceDB->getMapPageIdtoParentPageId(),
			$this->workspaceDB->getMapPageIdToConfluenceTitle(),
			$this->migrationConfig->getMainPageName()
		);

		$pages = $this->workspaceDB->getPages();
		$pageIdToWikiTitleMap = [];
		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'] ) ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					'Skipping page without page_id while updating wiki titles'
				);
				continue;
			}

			$pageId = (int)$page['page_id'];

			if ( !isset( $page['space_id'] ) || !isset( $page['confluence_title'] ) ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Skipping page $pageId while updating wiki titles: missing space_id or confluence_title"
				);
				continue;
			}

			// historical versions
			if ( (int)$page['original_version_id'] !== -1 ) {
				$this->dbLog->addLogEntry(
					'info',
					'extract',
					__CLASS__,
					"Skipping historical page $pageId while updating wiki titles"
				);
				continue;
			}

			// Skip pages that already have a wiki_title set (e.g. templates).
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
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

			if ( empty( $wikiTitle ) ) {
				$message = "TitleBuilder delivers empty wiki title for page $confluenceTitle (page id $pageId)";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);

				throw new Exception(
					$message
				);
			}
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			$this->dbLog->addLogEntry(
				'warning',
				'extract',
				__CLASS__,
				"Could not find page with wiki title"
			);
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $pageIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedPageIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $pageIdToWikiTitleMap );

		foreach ( $compressedPageIdToWikiTitleMap as $pageId => $wikiTitle ) {
			if ( empty( $wikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for page id $pageId";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);
				throw new Exception(
					$message
				);
			}
			$this->writeln(
				"Updated wiki title for page ID $pageId with title: $wikiTitle"
			);
			$this->workspaceDB->updatePageWikiTitle( (int)$pageId, $wikiTitle );

			$interwikiTitle = $this->getInterwikiTitle( (int)$pageId, $wikiTitle );
			$this->workspaceDB->updatePageInterwikiTitle( (int)$pageId, $interwikiTitle );
		}
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$titles = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$title = '';
			$pageId = $page['page_id'];
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
				$title = (string)$page['wiki_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		$validityChecker = new TitleValidityChecker();

		foreach ( $titles as $pageId => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->workspaceDB->addInvalidPageWikiTitle(
					$pageId, $title, 'Title ends with invalid character'
				);
			}

			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->workspaceDB->addInvalidPageWikiTitle(
						$pageId, $title, 'Title contains multiple colons'
					);
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->workspaceDB->addInvalidPageWikiTitle(
						$pageId, $title, 'Invalid namespace character detected'
					);
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->workspaceDB->addInvalidPageWikiTitle(
						$pageId, $title, 'Title contains too many characters (>255)'
					);
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->workspaceDB->addInvalidPageWikiTitle(
						$pageId, $title, 'Title contains too many characters (>255)'
					);
				}
			}
		}
	}

	private function getSpaceIdPrefixMap(): array {
		$spaceIdPrefixMap = [];
		foreach ( $this->workspaceDB->getSpaces() as $space ) {
			if ( !isset( $space['space_id'] ) || !isset( $space['space_key'] ) ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Skipping space without space_id or space_key while building spaceIdPrefixMap"
				);
				continue;
			}
			$spaceId = (int)$space['space_id'];
			$spaceKey = (string)$space['space_key'];

			$prefix = $this->getNamespaceForSpaceKey( $spaceKey );
			$prefix .= $this->getRootPage( $spaceKey );
			$spaceIdPrefixMap[$spaceId] = $prefix;
		}
		return $spaceIdPrefixMap;
	}

	/**
	 * @param string|null $spaceKey
	 * @return string
	 */
	private function getNamespaceForSpaceKey( ?string $spaceKey ): string {
		if ( empty( $spaceKey ) ) {
			return '';
		}

		$namespace = $this->wikiConfig->getNamespaceForSpaceKey( $spaceKey );
		if ( !empty( $namespace ) ) {
			// Ensure that the namespace ends with a colon
			$namespace = trim( $namespace, ':' ) . ':';
			return $namespace;
		}

		return '';
	}

	/**
	 * @param string|null $spaceKey
	 * @return string
	 */
	private function getRootPage( ?string $spaceKey ): string {
		if ( empty( $spaceKey ) ) {
			return '';
		}

		$rootpage = $this->wikiConfig->getRootPageForSpaceKey( $spaceKey );
		if ( !empty( $rootpage ) ) {
			// Ensure that the root page ends with a slash
			$rootpage = trim( $rootpage, '/' ) . '/';

			return $rootpage;
		}

		return '';
	}

	/**
	 * @param integer $pageId
	 * @param string $wikiTitle
	 * @return string
	 */
	private function getInterwikiTitle( int $pageId,string $wikiTitle ): string {
		$spaceId = $this->workspaceDB->getSpaceIdForPageId( $pageId );
		$spaceKey = $this->workspaceDB->getSpaceKeyFromSpaceId( $spaceId );
		$namespace = $this->getNamespaceForSpaceKey( $spaceKey );
		$interwikiPrefix = $this->wikiConfig->getInterwikiPrefixForSpaceKey( $spaceKey );

		$pageTitle = $wikiTitle;
		if ( !empty( $namespace ) ) {
			$pageTitle = substr( $wikiTitle, strlen( $namespace ) );
		}

		return $interwikiPrefix . ':' . $pageTitle;
	}
}
