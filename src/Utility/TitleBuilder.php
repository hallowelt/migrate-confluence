<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class TitleBuilder {

	/**
	 * @var int
	 */
	private int $currentTitlesSpaceHomePageId = -1;

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @param string $mainpage
	 */
	public function __construct(
		private array $spaceIdPrefixMap,
		private array $spaceIdHomepages,
		private array $pageIdParentPageIdMap,
		private array $pageIdConfluenceTitleMap,
		private string $mainpage = 'Main_Page'
	) {
	}

	/**
	 * @param int $spaceId
	 * @param int $pageId
	 * @param string $title
	 *
	 * @return string
	 * @throws InvalidTitleException
	 */
	public function buildTitle( int $spaceId, int $pageId, string $title ): string {
		$simpleSpaceIdPrefixMap = $this->simplifySpaceIdPrefixMap( $this->spaceIdPrefixMap );
		$builder = new GenericTitleBuilder( $simpleSpaceIdPrefixMap );
		$builder->setNamespace( $spaceId );

		$prefixRoot = '';
		if ( !str_ends_with( $this->spaceIdPrefixMap[$spaceId], ':' ) ) {
			$prefixRoot = substr(
				$this->spaceIdPrefixMap[$spaceId],
				strpos( $this->spaceIdPrefixMap[$spaceId], ':' ) + 1
			);
		}

		$this->currentTitlesSpaceHomePageId = -1;
		if ( isset( $this->spaceIdHomepages[$spaceId] ) ) {
			$this->currentTitlesSpaceHomePageId = $this->spaceIdHomepages[$spaceId];
		}

		if ( $pageId === $this->currentTitlesSpaceHomePageId && empty( $prefixRoot ) ) {
			$builder->appendTitleSegment( $this->mainpage );
			return $builder->build();
		} elseif ( $pageId === $this->currentTitlesSpaceHomePageId ) {
			$prefixRootParts = explode( '/', $prefixRoot );
			$PrefixRootPartsReverse = array_reverse( $prefixRootParts );
			foreach ( $PrefixRootPartsReverse as $prefixRootPart ) {
				$builder->appendTitleSegment( $prefixRootPart );
			}
			return $builder->build();
		}

		$titleParts = $this->addParentTitles( $pageId, $title );

		foreach ( $titleParts as $titlePart ) {
			$builder->appendTitleSegment( $titlePart );
		}

		if ( !empty( $prefixRoot ) ) {
			$prefixRootParts = explode( '/', $prefixRoot );
			$PrefixRootPartsReverse = array_reverse( $prefixRootParts );
			foreach ( $PrefixRootPartsReverse as $prefixRootPart ) {
				$builder->appendTitleSegment( $prefixRootPart );
			}
		}

		return $builder->invertTitleSegments()->build();
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @return array
	 */
	private function simplifySpaceIdPrefixMap( array $spaceIdPrefixMap ): array {
		$simpleMap = [];
		foreach ( $spaceIdPrefixMap as $spaceId => $prefix ) {
			if ( !str_ends_with( $prefix, ':' ) ) {
				$prefix = substr( $prefix, 0, strpos( $prefix, ':' ) + 1 );
			}
			$simpleMap[$spaceId] = $prefix;
		}
		return $simpleMap;
	}

	/**
	 * @param int $pageId
	 * @param string $title
	 * @return array
	 */
	private function addParentTitles( int $pageId, string $title ): array {
		$titles = [];
		if ( $pageId === $this->currentTitlesSpaceHomePageId ) {
				$titles[] = $this->mainpage;
		} else {
			$titles[] = $title;
		}

		if ( isset( $this->pageIdParentPageIdMap[$pageId] ) ) {
			$parentPageId = $this->pageIdParentPageIdMap[$pageId];
		} else {
			$parentPageId = null;
		}

		while ( $parentPageId !== null ) {
			if ( $parentPageId === $this->currentTitlesSpaceHomePageId ) {
				break;
			} elseif ( isset( $this->pageIdConfluenceTitleMap[$parentPageId] ) ) {
				$parentTitle = $this->pageIdConfluenceTitleMap[$parentPageId];
			} else {
				break;
			}

			$titles[] = $parentTitle;

			if ( isset( $this->pageIdParentPageIdMap[$parentPageId] ) ) {
				$parentPageId = $this->pageIdParentPageIdMap[$parentPageId];
			} else {
				$parentPageId = null;
			}
		}

		return $titles;
	}
}
