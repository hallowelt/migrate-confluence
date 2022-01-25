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
	 *
	 * @var integer
	 */
	private $currentTitlesSpaceHomePageId = -1;

	/**
	 *
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 */
	public function __construct( $spaceIdPrefixMap, $spaceIdHomepages, $helper ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdHomepages = $spaceIdHomepages;
		$this->helper = $helper;
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
			$this->builder->appendTitleSegment( 'Main_Page' );
			return $this->builder->build();
		}

		$titles = $this->addParentTitles( $pageNode );

		foreach ( $titles as $title ) {
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
		$title = $this->helper->getPropertyValue( 'title', $pageNode );

		$titles = [ $title ];

		$parentPageId = $this->helper->getPropertyValue( 'parent', $pageNode );
		if ( $parentPageId === $this->currentTitlesSpaceHomePageId ) {
			$parentPageId = null;
		}

		while ( is_int( $parentPageId ) ) {
			$parentPage = $this->helper->getObjectNodeById( $parentPageId, 'Page' );
			$parentTitle = $this->helper->getPropertyValue( 'title', $parentPage );

			$titles[] = $parentTitle;

			$parentPageId = $this->helper->getPropertyValue( 'parent', $parentPage );

			if ( $parentPageId === $this->currentTitlesSpaceHomePageId ) {
				$parentPageId = null;
			}
		}

		return $titles;
	}
}
