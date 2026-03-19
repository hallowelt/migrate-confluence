<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class TitleBuilder {

	/**
	 *
	 * @var GenericTitleBuilder
	 */
	private $builder = null;

	/**
	 *
	 * @var array
	 */
	private $spaceIdPrefixMap = [];

	/**
	 *
	 * @var array
	 */
	private $spaceIdHomepages = [];

	/**
	 * @var array
	 */
	private $pageIdParentPageIdMap = [];

	/**
	 * @var array
	 */
	private $pageIConfluenceTitledMap = [];

	/**
	 *
	 * @var int
	 */
	private $currentTitlesSpaceHomePageId = -1;

	/**
	 * @var string
	 */
	private $mainpage = '';

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIConfluenceTitledMap
	 * @param string $mainpage
	 */
	public function __construct(
		array $spaceIdPrefixMap, array $spaceIdHomepages, array $pageIdParentPageIdMap,
		array $pageIConfluenceTitledMap, string $mainpage = 'Main_Page'
	) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdHomepages = $spaceIdHomepages;
		$this->pageIdParentPageIdMap = $pageIdParentPageIdMap;
		$this->pageIConfluenceTitledMap = $pageIConfluenceTitledMap;
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
		if ( empty( $title ) ) {
			throw new InvalidTitleException( 'Title is empty' );
		}

		$this->builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$this->builder->setNamespace( $spaceId );

		$this->currentTitlesSpaceHomePageId = -1;
		if ( isset( $this->spaceIdHomepages[$spaceId] ) ) {
			$this->currentTitlesSpaceHomePageId = $this->spaceIdHomepages[$spaceId];
		}

		if ( $pageId === $this->currentTitlesSpaceHomePageId ) {
			$this->builder->appendTitleSegment( $this->mainpage );
			return $this->builder->build();
		}

		$titles = $this->addParentTitles( $pageId, $title );

		foreach ( $titles as $title ) {
			$title = str_replace(
				[ ':', '%', '?', '#', '<', '>', '+', '[', ']', '{', '}', '|' ],
				'_',
				$title
			);
			$title = str_replace( '__', '_', $title );
			$this->builder->appendTitleSegment( $title );
		}

		return $this->builder->invertTitleSegments()->build();
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
			} else {
				$parentTitle = $this->pageIConfluenceTitledMap[$parentPageId];
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
