<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class TitleBuilder {

	/**
	 * @var array
	 */
	private array $spaceIdPrefixMap;

	/**
	 *
	 * @var array
	 */
	private array $spaceIdHomepages;

	/**
	 * @var array
	 */
	private array $pageIdParentPageIdMap;

	/**
	 * @var array
	 */
	private array $pageIdConfluenceTitleMap;

	/**
	 *
	 * @var int
	 */
	private int $currentTitlesSpaceHomePageId = -1;

	/**
	 * @var string
	 */
	private string $mainpage;

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @param string $mainpage
	 */
	public function __construct(
		array $spaceIdPrefixMap, array $spaceIdHomepages, array $pageIdParentPageIdMap,
		array $pageIdConfluenceTitleMap, string $mainpage = 'Main_Page'
	) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdHomepages = $spaceIdHomepages;
		$this->pageIdParentPageIdMap = $pageIdParentPageIdMap;
		$this->pageIdConfluenceTitleMap = $pageIdConfluenceTitleMap;
		$this->mainpage = $mainpage;
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
		$builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$builder->setNamespace( $spaceId );

		$this->currentTitlesSpaceHomePageId = -1;
		if ( isset( $this->spaceIdHomepages[$spaceId] ) ) {
			$this->currentTitlesSpaceHomePageId = $this->spaceIdHomepages[$spaceId];
		}

		if ( $pageId === $this->currentTitlesSpaceHomePageId ) {
			$builder->appendTitleSegment( $this->mainpage );
			return $builder->build();
		}

		$titleParts = $this->addParentTitles( $pageId, $title );

		foreach ( $titleParts as $titlePart ) {
			$builder->appendTitleSegment( $titlePart );
		}

		return $builder->invertTitleSegments()->build();
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
