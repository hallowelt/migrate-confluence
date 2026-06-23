<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ComposerSkipHelper {

	/** @var array */
	private array $pageWikiTitleToPageIdMap = [];

	/** @var array */
	private array $blogPostWikiTitleToBlogPostIdMap = [];

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private DBComposerDataLookup $dataLookup,
		private MigrationConfig $migrationConfig
	) {
		$pageIdToTitleMap = $this->dataLookup->getPageIdWikiPageTitleMap();
		$this->pageWikiTitleToPageIdMap = array_flip( $pageIdToTitleMap );

		$blogPostIdToTitleMap = $this->dataLookup->getBlogPostIdWikiBlogPostTitleMap();
		$this->blogPostWikiTitleToBlogPostIdMap = array_flip( $blogPostIdToTitleMap );
	}

	/**
	 * @param int $pageId
	 * @return bool
	 */
	public function skipPageById( int $pageId ): bool {
		if ( $this->dataLookup->isPageInvalid( $pageId ) ) {
			return true;
		}

		$wikiTitle = $this->dataLookup->getWikiPageTitleFromPageId( $pageId );
		if ( !$wikiTitle ) {
			return true;
		}
		return $this->skipWikiTitleByConfiguration( $wikiTitle );
	}

	/**
	 * @param int $blogPostId
	 * @return bool
	 */
	public function skipBlogPostById( int $blogPostId ): bool {
		if ( $this->dataLookup->isBlogPostInvalid( $blogPostId ) ) {
			return true;
		}

		$wikiTitle = $this->dataLookup->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
		if ( !$wikiTitle ) {
			return true;
		}

		return $this->skipWikiTitleByConfiguration( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 * @return bool
	 */
	public function skipWikiTitle( string $wikiTitle ): bool {
		if ( str_starts_with( $wikiTitle, 'Blog:' ) ) {
			// Blog page title
			if ( !isset( $this->blogPostWikiTitleToBlogPostIdMap[$wikiTitle] ) ) {
				return true;
			}
			$blogPostId = $this->blogPostWikiTitleToBlogPostIdMap[$wikiTitle];
			return $this->skipBlogPostById( $blogPostId );
		} elseif ( str_starts_with( $wikiTitle, 'Template:' ) ) {
			// Template page title
			return $this->skipWikiTitleByConfiguration( $wikiTitle );
		} else {
			// Content page title
			if ( !isset( $this->pageWikiTitleToPageIdMap[$wikiTitle] ) ) {
				return true;
			}
			$pageId = $this->pageWikiTitleToPageIdMap[$wikiTitle];
			return $this->skipPageById( $pageId );
		}
	}

	/**
	 * Skip wiki title by configuration
	 *
	 * @param string $wikiTitle
	 * @return bool
	 */
	private function skipWikiTitleByConfiguration( string $wikiTitle ): bool {
		$namespace = 'NS_MAIN';
		if ( str_contains( $wikiTitle, ':' ) ) {
			$namespace = substr( $wikiTitle, 0, strpos( $wikiTitle, ':' ) );
		}

		if ( $this->skipNamespaceByConfiguration( $namespace ) ) {
			return true;
		}

		if ( in_array( $wikiTitle, $this->migrationConfig->getComposerSkipTitles() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Skip wiki namespace by configuration
	 * Main namespace can be skipped with NS_MAIN
	 *
	 * @param string $namespace
	 * @return bool
	 */
	public function skipNamespaceByConfiguration( string $namespace ): bool {
		if ( in_array( $namespace, $this->migrationConfig->getComposerSkipNamespaces() ) ) {
			return true;
		}
		return false;
	}
}
