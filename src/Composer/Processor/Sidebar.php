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
	 * @param string $namespace The result sub-directory (e.g. "NS_MAIN", "MY_NS").
	 * @param array $spaces Spaces belonging to this namespace, each with 'space_id' and 'space_name'.
	 * @return void
	 */
	public function execute( string $namespace, array $spaces ): void {
		if ( !$this->migrationConfig->getCreateSidebar() ) {
			return;
		}

		$sidebar = [];
		foreach ( $spaces as $space ) {
			$section = $this->buildSpaceSection( $space );
			if ( $section !== null ) {
				$sidebar[] = $section;
			}
		}

		if ( $sidebar === [] ) {
			return;
		}

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

		$resultPath = $this->dest . '/result/' . $namespace . '/';
		if ( !is_dir( $resultPath ) ) {
			mkdir( $resultPath, 0755, true );
		}
		$builder->buildAndSave( $resultPath . 'enhanced-sidebar.xml' );
	}

	/**
	 * Builds a top-level section for a single space.
	 *
	 * Structure:
	 * - Space heading (always)
	 *   - If both pages and blogs exist: "Pages" and "Blogs" sub-headings
	 *   - If only one type: entries directly under the space heading (no sub-heading)
	 *
	 * Returns null when the space has no content.
	 *
	 * @param array $space
	 * @return array|null
	 */
	private function buildSpaceSection( array $space ): ?array {
		$spaceId = (int)$space['space_id'];
		$spaceName = (string)$space['space_name'];

		$pageEntries = $this->buildPageTree( $this->dataLookup->getPagesForSidebar( $spaceId ) );
		$blogEntries = $this->buildBlogList( $this->dataLookup->getBlogPostsForSidebar( $spaceId ) );

		if ( $pageEntries === [] && $blogEntries === [] ) {
			return null;
		}

		if ( $pageEntries !== [] && $blogEntries !== [] ) {
			$children = [
				$this->buildSection( 'Pages', $pageEntries ),
				$this->buildSection( 'Blogs', $blogEntries ),
			];
		} else {
			$children = $pageEntries !== [] ? $pageEntries : $blogEntries;
		}

		return $this->buildSection( $spaceName, $children );
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
