<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;

class FilenameBuilder {

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
	 * @param array $spaceIdPrefixMap
	 * @param XMLHelper $helper
	 */
	public function __construct( $spaceIdPrefixMap, $helper ) {
		$this->helper = $helper;
		$this->builder = new GenericTitleBuilder( $spaceIdPrefixMap );
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return string
	 */
	public function buildFilename( $pageNode ) {
		$spaceId = $this->getSpaceId( $pageNode );
		$this->builder->setNamespace( $spaceId );

		$title = $this->helper->getPropertyValue( 'fileName', $pageNode );
		if ( empty( $title ) ) {
			$title = $this->helper->getPropertyValue( 'title', $pageNode );
		}
		$this->builder->appendTitleSegment( $title );
		$builtTitle = $this->builder->invertTitleSegments()->build();
		$filename = new WindowsFilename( $builtTitle );

		return (string) $filename;
	}


	/**
	 *
	 * @param BSConfluenceXMLHelper $this->helper
	 * @param DOMElement $pageNode
	 * @return int
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
}