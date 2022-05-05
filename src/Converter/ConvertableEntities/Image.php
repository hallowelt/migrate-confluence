<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

use DOMDocument;
use DOMNode;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Converter\IProcessable;
use HalloWelt\MigrateConfluence\Utility\Html;

class Image implements IProcessable {

	/**
	 *
	 * @var ConversionDataLookup
	 */
	private $dataLookup = null;

	/**
	 *
	 * @var integer
	 */
	private $currentSpaceId = -1;

	/**
	 *
	 * @var string
	 */
	private $rawPageTitle = '';

	/**
	 * @var boolean
	 */
	private $nsFileRepoCompat = false;

	/**
	 *
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( $dataLookup, $currentSpaceId, $rawPageTitle, $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->nsFileRepoCompat = $nsFileRepoCompat;
	}

	/**
	 * @inheritDoc
	 * Processes image Confluence entity. Converts it to "img" tag if source is some external link,
	 * and to [[File:...]] link if source is attachment.
	 *
	 * Possible attributes to convert:
	 * * "ac:width"
	 * * "ac:height"
	 * * "ac:thumbnail"
	 * * "ac:align"
	 * * "ac:alt"
	 *
	 * @see https://confluence.atlassian.com/doc/confluence-storage-format-790796544.html#ConfluenceStorageFormat-Images
	 */
	public function process( ?ConfluenceConverter $sender, DOMNode $match, DOMDocument $dom, DOMXPath $xpath ): void {
		$attachmentEl = $xpath->query( './ri:attachment', $match )->item( 0 );
		$urlEl = $xpath->query( './ri:url', $match )->item( 0 );

		// For a potential WikiText-Image-Link
		$params = [];
		// For a potential HTML <img> element
		$attribs = [];

		$width = $match->getAttribute( 'ac:width' );
		$height = $match->getAttribute( 'ac:height' );
		if ( $width !== '' || $height !== '' ) {
			$dimensions = 'px';
			if ( $height !== '' ) {
				$dimensions = 'x' . $height . $dimensions;
				$attribs['height'] = $height;
			}
			$dimensions = $width . $dimensions;
			$params[] = $dimensions;
			if ( $width !== '' ) { $attribs['width'] = $width;
			}
		}

		if ( $match->getAttribute( 'ac:class' ) !== '' ) {
			$attribs['class'][] = $match->getAttribute( 'ac:class' );
		}
		if ( $match->getAttribute( 'ac:thumbnail' ) !== '' ) {
			$params[] = 'thumb';
			$attribs['class'][] = 'thumb';
		}
		if ( $match->getAttribute( 'ac:align' ) !== '' ) {
			$params[] = $match->getAttribute( 'ac:align' );
			$attribs['align'] = $match->getAttribute( 'ac:align' );
		}
		if ( $match->getAttribute( 'ac:alt' ) !== '' ) {
			// $params[] = $match->getAttribute('ac:alt');
			$attribs['alt'] = $match->getAttribute( 'ac:alt' );
		}

		if ( !empty( $attribs['class'] ) ) {
			$attribs['class'] = implode( ' ', $attribs['class'] );
		}

		$replacement = '[[Category:Broken_image]]';
		if ( $urlEl instanceof DOMNode ) {
			$attribs['src'] = $urlEl->getAttribute( 'ri:value' );
			$replacement = $this->makeImageTag( $dom, $attribs );
		} elseif ( $attachmentEl instanceof DOMNode ) {
			$riFilename = $attachmentEl->getAttribute( 'ri:filename' );
			$nestedPageEl = $xpath->query( './ri:page', $attachmentEl )->item( 0 );
			$rawPageTitle = $this->rawPageTitle;
			$spaceId = $this->currentSpaceId;
			if ( $nestedPageEl instanceof DOMElement ) {
				$rawPageTitle = $nestedPageEl->getAttribute( 'ri:content-title' );
				$spaceKey = $nestedPageEl->getAttribute( 'ri:space-key' );
				if ( !empty( $spaceKey ) ) {
					$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
				}
			}
			$rawPageTitle = basename( $rawPageTitle );
			$confluenceFileKey = "$spaceId---$rawPageTitle---$riFilename";
			$targetFilename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );
			array_unshift( $params, $targetFilename );
			$replacement = $this->makeImageLinkWithDebugInfo( $dom, $params, $confluenceFileKey );
		}

		$match->parentNode->replaceChild(
			$replacement,
			$match
		);
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $aAttributes
	 * @return DOMNode
	 */
	public function makeImageTag( DOMDocument $dom, array $aAttributes ): DOMNode {
		return Html::element( $dom, 'img', $aAttributes );
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @return DOMNode
	 */
	public function makeImageLink( DOMDocument $dom, array $params ): DOMNode {
		$params = array_map( 'trim', $params );
		return $dom->createTextNode( '[[File:' . implode( '|', $params ) . ']]' );
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @param string $confluenceFileKey
	 * @return DOMNode
	 */
	private function makeImageLinkWithDebugInfo( DOMDocument $dom, array $params, $confluenceFileKey ): DOMNode {
		$params = array_map( 'trim', $params );
		$debug = '';
		if ( empty( $params ) || empty( $params[0] ) ) {
			$debug = " ###BROKENIMAGE $confluenceFileKey ###";
		} elseif ( $this->nsFileRepoCompat === true ) {
			$filename = $params[0];
			$pos = strpos( $filename, '_' );
			if ( $pos !== false ) {
				$namespace = substr( $filename, 0, $pos );
				if ( $namespace !== false ) {
					$params[0] = str_replace( $namespace . '_', $namespace . ':', $filename );
				}
			}
		}
		return $dom->createTextNode( '[[File:' . implode( '|', $params ) . ']]' . $debug );
	}
}
