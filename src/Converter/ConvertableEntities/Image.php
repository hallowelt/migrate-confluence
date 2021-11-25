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
	 * Image DOMNode to be processed.
	 *
	 * @var DOMNode
	 */
	private $image;

	/**
	 * Image constructor.
	 * @param DOMNode $image
	 */
	/*public function __construct( DOMNode $image)
	{
		$this->image = $image;
	}*/

	/**
	 * {@inheritDoc}
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

		$params = []; // For a potential WikiText-Image-Link
		$attribs = []; // For a potential HTML <img> element

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
			$replacement = self::makeImageTag( $dom, $attribs );
		} elseif ( $attachmentEl instanceof DOMNode ) {
			array_unshift( $params, $attachmentEl->getAttribute( 'ri:filename' ) );
			$replacement = self::makeImageLink( $dom, $params );
		}

		$match->parentNode->replaceChild(
			$replacement,
			$match
		);
	}

	/**
	 * @param DOMDocument $dom
	 * @param $aAttributes
	 * @return DOMNode
	 */
	public function makeImageTag( DOMDocument $dom, array $aAttributes ): DOMNode {
		return Html::element( $dom, 'img', $aAttributes );
	}

	/**
	 * @param DOMDocument $dom
	 * @param $params
	 * @return DOMNode
	 */
	public function makeImageLink( DOMDocument $dom, array $params ): DOMNode {
		$params = array_map( 'trim', $params );
		return $dom->createTextNode( '[[File:' . implode( '|', $params ) . ']]' );
	}
}
