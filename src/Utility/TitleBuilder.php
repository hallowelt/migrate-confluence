<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class TitleBuilder {

	/**
	 * @var XMLHelper
	 */
	private $helper = null;

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
	 * @param XMLHelper $helper
	 * @param string $mainpage
	 */
	public function __construct(
		array $spaceIdPrefixMap, array $spaceIdHomepages, array $pageIdParentPageIdMap,
		array $pageIConfluenceTitledMap, XMLHelper $helper, string $mainpage = 'Main_Page'
	) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdHomepages = $spaceIdHomepages;
		$this->pageIdParentPageIdMap = $pageIdParentPageIdMap;
		$this->pageIConfluenceTitledMap = $pageIConfluenceTitledMap;
		$this->helper = $helper;
		$this->mainpage = $mainpage;
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return string
	 */
	public function buildTitle( $pageNode ) {
		$spaceId = $this->getSpaceId( $pageNode );
		$this->builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$this->builder->setNamespace( $spaceId );

		$pageId = $this->helper->getIDNodeValue( $pageNode );
		$this->currentTitlesSpaceHomePageId = -1;
		if ( isset( $this->spaceIdHomepages[$spaceId] ) ) {
			$this->currentTitlesSpaceHomePageId = $this->spaceIdHomepages[$spaceId];
		}

		if ( $pageId === $this->currentTitlesSpaceHomePageId ) {
			$this->builder->appendTitleSegment( $this->mainpage );
			return $this->builder->build();
		}

		$titles = $this->addParentTitles( $pageNode );

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
	 *
	 * @param DOMElement $pageNode
	 * @return int
	 */
	private function getSpaceId( $pageNode ) {
		$spaceId = $this->helper->getPropertyValue( 'space', $pageNode );
		if ( is_int( $spaceId ) ) {
			return $spaceId;
		}

		$originalVersion = $this->helper->getPropertyValue( 'originalVersion', $pageNode );
		if ( $originalVersion !== null ) {
			$origPage = $this->helper->getObjectNodeById( $originalVersion, 'Page' );
			return $this->getSpaceId( $origPage );
		}
		return 0;
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return array
	 */
	private function addParentTitles( $pageNode ) {
		$pageId = $this->helper->getIDNodeValue( $pageNode );
		$title = $this->helper->getPropertyValue( 'title', $pageNode );

		$titles = [];
		if ( $pageId === $this->currentTitlesSpaceHomePageId ) {
				$title[] = $this->mainpage;
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
