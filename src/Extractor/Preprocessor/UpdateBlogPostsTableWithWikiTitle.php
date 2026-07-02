<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;

/**
 */
class UpdateBlogPostsTableWithWikiTitle extends ProcessorBase {

	private const NS_BLOG_NAME = 'Blog';

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
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
	 *
	 * @throws Exception
	 */
	private function updateWikiTitles(): void {
		$spaceIdToPrefixMap = $this->workspaceDB->getMapSpaceIdToPrefix();
		$blogPosts = $this->workspaceDB->getBlogPosts();
		$pageIdToWikiTitleMap = [];

		foreach ( $blogPosts as $blogPost ) {
			if (
				!isset( $blogPost['page_id'] )
				|| !isset( $blogPost['space_id'] )
				|| !isset( $blogPost['confluence_title'] )
				// historical versions
				|| (int)$blogPost['original_version_id'] !== -1
			) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];
			$spaceId = (int)$blogPost['space_id'];
			$confluenceTitle = (string)$blogPost['confluence_title'];

			if ( !isset( $spaceIdToPrefixMap[$spaceId] ) ) {
				continue;
			}

			$this->writeln(
				"Creating wiki title for blog post ID $pageId with confluence title '$confluenceTitle'"
			);

			$blogTitlePrefix = $this->getBlogTitlePrefixFromSpacePrefix( $spaceIdToPrefixMap[$spaceId] );
			$titleBuilder = new TitleBuilder( [ $spaceId => $blogTitlePrefix ], [], [], [] );

			try {
				$wikiTitle = $titleBuilder->buildTitle( $spaceId, $pageId, $confluenceTitle );
				$pageIdToWikiTitleMap[$pageId] = $wikiTitle;
			} catch ( Exception $ex ) {
				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					'Could not build wiki title for blog post ' . $pageId . ': ' . $ex->getMessage()
				);
			}

			if ( empty( $wikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for blog post id $pageId";

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
				"Could not find blog post with wiki title"
			);
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $pageIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedPageIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $pageIdToWikiTitleMap );

		foreach ( $compressedPageIdToWikiTitleMap as $pageId => $wikiTitle ) {
			if ( empty( $wikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for blog post id $pageId";

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
				"Updated wiki title for blog post ID $pageId with title: $wikiTitle"
			);
			$this->workspaceDB->updateBlogPostWikiTitle( (int)$pageId, $wikiTitle );

			$interwikiTitle = $this->getInterwikiTitle( (int)$pageId, $wikiTitle );
			$this->workspaceDB->updateBlogPostInterwikiTitle( (int)$pageId, $interwikiTitle );
		}
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$titles = [];
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$title = '';
			$pageId = $blogPost['page_id'];
			if ( isset( $blogPost['wiki_title'] ) && $blogPost['wiki_title'] !== '' ) {
				$title = (string)$blogPost['wiki_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		$validityChecker = new TitleValidityChecker();

		foreach ( $titles as $pageId => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->workspaceDB->addInvalidBlogPostWikiTitle(
					$pageId, $title, 'Title ends with invalid character'
				);
			}

			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->workspaceDB->addInvalidBlogPostWikiTitle(
						$pageId, $title, 'Title contains multiple colons'
					);
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->workspaceDB->addInvalidBlogPostWikiTitle(
						$pageId, $title, 'Invalid namespace character detected'
					);
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->workspaceDB->addInvalidBlogPostWikiTitle(
						$pageId, $title, 'Title contains too many characters (>255)'
					);
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->workspaceDB->addInvalidBlogPostWikiTitle(
						$pageId, $title, 'Title contains too many characters (>255)'
					);
				}
			}
		}
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @return string
	 */
	private function getInterwikiTitle( int $pageId, string $wikiTitle ): string {
		$spaceId = $this->workspaceDB->getSpaceIdForBlogPostId( $pageId );
		if ( $spaceId === null ) {
			return $wikiTitle;
		}

		$spaceKey = $this->workspaceDB->getSpaceKeyFromSpaceId( $spaceId );
		if ( $spaceKey === null || $spaceKey === '' ) {
			return $wikiTitle;
		}

		$interwikiPrefix = $this->wikiConfig->getInterwikiPrefixForSpaceKey( $spaceKey );
		$pageTitle = $wikiTitle;
		$blogTitlePrefix = $this->getBlogTitlePrefixForSpaceKey( $spaceKey );
		if ( !str_starts_with( $pageTitle, $blogTitlePrefix ) ) {
			return $wikiTitle;
		}

		$pageTitle = substr( $pageTitle, strlen( self::NS_BLOG_NAME . ':' ) );

		return "$interwikiPrefix-blog:$pageTitle";
	}

	/**
	 * @param string $spacePrefix
	 * @return string
	 */
	private function getBlogTitlePrefixFromSpacePrefix( string $spacePrefix ): string {
		$prefixRoot = '';
		if ( $spacePrefix !== '' && str_contains( $spacePrefix, ':' ) ) {
			$prefixRoot = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) ) . '/';
		}

		return self::NS_BLOG_NAME . ':' . $prefixRoot;
	}

	/**
	 * @param string $spaceKey
	 * @return string
	 */
	private function getBlogTitlePrefixForSpaceKey( string $spaceKey ): string {
		$spacePrefix = $this->workspaceDB->getSpacePrefixFromSpaceKey( $spaceKey );
		return $this->getBlogTitlePrefixFromSpacePrefix( $spacePrefix );
	}
}
