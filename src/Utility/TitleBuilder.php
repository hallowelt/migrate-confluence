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
	private $spaceIdPrefixMap = null;

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
	public function buildTitle( $pageNode ) {
		$spaceId = $this->getSpaceId( $pageNode );
		$this->builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$this->builder->setNamespace( $spaceId );

		$title = $this->helper->getPropertyValue( 'title', $pageNode );
		$this->builder->appendTitleSegment( $title );

		$this->addParentTitles( $pageNode );

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
		$parentPageId = $this->helper->getPropertyValue( 'parent', $pageNode );
		if( is_integer( $parentPageId ) ) {
			$parentPage = $this->helper->getObjectNodeById( $parentPageId, 'Page' );
			$title = $this->helper->getPropertyValue( 'title', $parentPage );
			$position = $this->helper->getPropertyValue( 'position', $parentPage );
			if( !empty( $position ) ) {
				$title = str_pad( $position, 3, '0', STR_PAD_BOTH ).'_'.$title;
			}

			$this->addParentTitles( $parentPage );
		}
	}
}