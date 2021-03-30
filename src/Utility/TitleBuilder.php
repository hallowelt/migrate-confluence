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
	 * @param array $spaceIdPrefixMap
	 * @param XMLHelper $helper
	 */
	public function __construct( $spaceIdPrefixMap, $helper ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->helper = $helper;
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return string
	 */
	public function buildTitle( $pageNode, $fullTitle = true ) {
		$spaceId = $this->getSpaceId( $pageNode );
		$this->builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$this->builder->setNamespace( $spaceId );

		if( $fullTitle ) {
			$titles = $this->addParentTitles( $pageNode );
			$fullTitle = implode( '/', array_reverse( $titles ) );
		}
		else {
			$fullTitle = $this->helper->getPropertyValue( 'title', $pageNode );
		}

		$this->builder->appendTitleSegment( $fullTitle );

		return $this->builder->invertTitleSegments()->build();
	}


	/**
	 *
	 * @param BSConfluenceXMLHelper $this->helper
	 * @param DOMElement $pageNode
	 * 	 * @return int
	 */
	private function getSpaceId( $pageNode) {
		$spaceId = $this->helper->getPropertyValue( 'space', $pageNode );
		if( is_int( $spaceId ) ) {
			return $spaceId;
		}

		$originalVersion = $this->helper->getPropertyValue( 'originalVersion', $pageNode );
		if( $originalVersion !== null ) {
			$origPage = $this->helper->getObjectNodeById( $originalVersion , 'Page' );
			return $this->getSpaceId( $origPage );
		}
		return 0;
	}

	private function addParentTitles( $pageNode ) {
		$title = $this->helper->getPropertyValue( 'title', $pageNode );

		$titles = [ $title ];

		$parentPageId = $this->helper->getPropertyValue( 'parent', $pageNode );
		while( is_integer( $parentPageId ) ) {
			$parentPage = $this->helper->getObjectNodeById( $parentPageId, 'Page' );
			$parentTitle = $this->helper->getPropertyValue( 'title', $parentPage );

			$titles[] = $parentTitle;

			$parentPageId = $this->helper->getPropertyValue( 'parent', $parentPage );
		}

		return $titles;
	}
}