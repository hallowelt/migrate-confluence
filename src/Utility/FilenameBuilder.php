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
	 * @param DOMElement $attachmentNode
	 * @param string $assocTitle
	 * @return string
	 */
	public function buildFilename( $attachmentNode, $assocTitle ) {
		$this->builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );

		// In some cases attachments don't have a `space`-id set. We fall back to NS_MAIN then
		$spaceId = $this->getSpaceId( $attachmentNode );
		$this->builder->setNamespace( $spaceId );

		$title = $this->helper->getPropertyValue( 'fileName', $attachmentNode );
		if ( empty( $title ) ) {
			$title = $this->helper->getPropertyValue( 'title', $attachmentNode );
		}
		$this->builder->appendTitleSegment( $title );
		if ( !empty( $assocTitle ) ) {
			$this->builder->appendTitleSegment( $assocTitle );
		}
		$builtTitle = $this->builder->invertTitleSegments()->build();

		$builtTitle = str_replace( '/', '_', $builtTitle );

		$filename = new WindowsFilename( $builtTitle );

		return (string)$filename;
	}

	/**
	 *
	 * @param DOMElement $attachmentNode
	 * @return int
	 */
	private function getSpaceId( $attachmentNode ) {
		$spaceId = $this->helper->getPropertyValue( 'space', $attachmentNode );
		if ( is_int( $spaceId ) ) {
			return $spaceId;
		}

		$originalVersion = $this->helper->getPropertyValue( 'originalVersion', $attachmentNode );
		if ( $originalVersion !== null ) {
			$origPage = $this->helper->getObjectNodeById( $originalVersion, 'Page' );
			return $this->getSpaceId( $origPage );
		}

		return 0;
	}
}
