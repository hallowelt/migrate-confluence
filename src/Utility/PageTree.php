<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * Shared view of the Confluence page hierarchy, used by every output that mirrors it
 * (enhanced sidebar, page hierarchy import metadata).
 */
class PageTree {

	/**
	 * Groups pages by parent and sorts siblings by position. Pages without a parent, or whose
	 * parent is not in the list (e.g. outside the space), are listed under key 0 (root).
	 *
	 * @param array $pages Flat list of ['page_id', 'parent_page_id', 'position', ...]
	 * @return array<int, array[]> Parent page ID (0 = root) => ordered child pages
	 */
	public static function childrenByParent( array $pages ): array {
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

		return $childrenOf;
	}
}
