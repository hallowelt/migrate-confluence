<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class Sidebar {

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param MigrationConfig $migrationConfig
	 * @param string $dest Destination workspace directory
	 */
	public function __construct(
		private DBComposerDataLookup $dataLookup,
		private MigrationConfig $migrationConfig,
		private string $dest
	) {
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		if ( !$this->migrationConfig->getCreateSidebar() ) {
			return;
		}

		$spaces = $this->dataLookup->getSpaces();
		$sidebar = count( $spaces ) > 1
			? $this->buildMultiSpaceSidebar( $spaces )
			: $this->buildSingleSpaceSidebar( $spaces );

		$json = json_encode( $sidebar, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$builder = new Builder();
		$builder->addRevision(
			'MediaWiki:Sidebar.json',
			$json,
			'',
			'',
			'json',
			'application/json'
		);

		$resultPath = $this->dest . '/result/';
		if ( !is_dir( $resultPath ) ) {
			mkdir( $resultPath, 0755, true );
		}
		$builder->buildAndSave( $resultPath . 'enhanced-sidebar.xml' );
	}

	/**
	 * Single space: top-level "Pages" and "Blogs" sections (omitting empty ones).
	 *
	 * @param array $spaces
	 * @return array
	 */
	private function buildSingleSpaceSidebar( array $spaces ): array {
		$spaceId = !empty( $spaces ) ? (int)$spaces[0]['space_id'] : null;
		return $this->buildPagesBlogsSections( $spaceId );
	}

	/**
	 * Multiple spaces: top-level per-space headings, each containing "Pages"/"Blogs" sections.
	 *
	 * @param array $spaces
	 * @return array
	 */
	private function buildMultiSpaceSidebar( array $spaces ): array {
		$sidebar = [];
		foreach ( $spaces as $space ) {
			$spaceId = (int)$space['space_id'];
			$spaceName = (string)$space['space_name'];

			$sections = $this->buildPagesBlogsSections( $spaceId );
			if ( $sections === [] ) {
				continue;
			}

			$sidebar[] = $this->buildSection( $spaceName, $sections );
		}
		return $sidebar;
	}

	/**
	 * Builds "Pages" and/or "Blogs" sections for a space, skipping empty ones.
	 *
	 * @param int|null $spaceId
	 * @return array
	 */
	private function buildPagesBlogsSections( ?int $spaceId ): array {
		$sections = [];

		$pageEntries = $this->buildPageTree( $this->dataLookup->getPagesForSidebar( $spaceId ) );
		if ( $pageEntries !== [] ) {
			$sections[] = $this->buildSection( 'Pages', $pageEntries );
		}

		$blogEntries = $this->buildBlogList( $this->dataLookup->getBlogPostsForSidebar( $spaceId ) );
		if ( $blogEntries !== [] ) {
			$sections[] = $this->buildSection( 'Blogs', $blogEntries );
		}

		return $sections;
	}

	/**
	 * @param array $pages Flat list of ['page_id', 'wiki_title', 'parent_page_id', 'position']
	 * @return array Nested sidebar link entries
	 */
	private function buildPageTree( array $pages ): array {
		$pageIdSet = array_flip( array_column( $pages, 'page_id' ) );

		$childrenOf = [];
		foreach ( $pages as $page ) {
			$parentId = $page['parent_page_id'];
			if ( $parentId === -1 || !isset( $pageIdSet[$parentId] ) ) {
				$parentId = 0;
			}
			$childrenOf[$parentId][] = $page;
		}

		foreach ( $childrenOf as &$siblings ) {
			usort( $siblings, static fn ( $a, $b ) => $a['position'] <=> $b['position'] );
		}
		unset( $siblings );

		return $this->buildEntries( $childrenOf[0] ?? [], $childrenOf );
	}

	/**
	 * @param array $blogs Flat list of ['page_id', 'wiki_title', 'confluence_title']
	 * @return array Flat sidebar link entries
	 */
	private function buildBlogList( array $blogs ): array {
		$entries = [];
		foreach ( $blogs as $blog ) {
			$entries[] = $this->buildLink( $blog['wiki_title'], $blog['confluence_title'] );
		}
		return $entries;
	}

	/**
	 * @param array $pages Siblings to render
	 * @param array $childrenOf Full map of parentId → children
	 * @return array
	 */
	private function buildEntries( array $pages, array $childrenOf ): array {
		$entries = [];
		foreach ( $pages as $page ) {
			$entry = $this->buildLink( $page['wiki_title'], $page['confluence_title'] );
			$kids = $childrenOf[$page['page_id']] ?? [];
			if ( $kids ) {
				$entry['children'] = $this->buildEntries( $kids, $childrenOf );
			}
			$entries[] = $entry;
		}
		return $entries;
	}

	/**
	 * @param string $wikiTitle
	 * @param string $confluenceTitle
	 * @return array
	 */
	private function buildLink( string $wikiTitle, string $confluenceTitle ): array {
		return [
			'type'     => 'enhanced-sidebar-internal-link',
			'text'     => $confluenceTitle,
			'hidden'   => '',
			'classes'  => [],
			'icon-cls' => '',
			'page'     => $wikiTitle,
		];
	}

	/**
	 * @param string $text
	 * @param array $children
	 * @return array
	 */
	private function buildSection( string $text, array $children ): array {
		return [
			'type'     => 'enhanced-sidebar-panel-heading',
			'text'     => $text,
			'hidden'   => '',
			'classes'  => [],
			'icon-cls' => '',
			'children' => $children,
		];
	}
}
