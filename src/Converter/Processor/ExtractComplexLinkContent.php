<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Cleans up <a> elements that contain <br/> or <ac:image> anywhere in their
 * subtree, which pandoc cannot reliably convert to valid wikitext links.
 *
 * Two transformations are applied (independently):
 *
 * 1. <br/> removal: any <br/> found anywhere inside an <a> is simply removed,
 *    because pandoc turns <br/> inside a link into a newline, which breaks the
 *    MediaWiki link syntax.
 *
 * 2. Image promotion: when an <a> contains an <ac:image> anywhere in its
 *    subtree, the result is normalised to:
 *
 *      <a href="url"><ac:image/></a><other content>
 *
 *    so the Image processor can reliably handle the image-in-link case
 *    (it requires <ac:image> to be a direct and sole child of <a>).
 *    All collected <ac:image> elements are promoted to direct children of <a>;
 *    everything else is moved to immediately after <a>.
 *
 * Links that contain neither <br/> nor <ac:image> are left completely untouched.
 */
class ExtractComplexLinkContent implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$anchors = $dom->getElementsByTagName( 'a' );

		$anchorList = [];
		foreach ( $anchors as $anchor ) {
			$anchorList[] = $anchor;
		}

		foreach ( $anchorList as $anchor ) {
			if ( !$anchor instanceof DOMElement ) {
				continue;
			}

			$this->removeBrElements( $anchor );

			$images = $this->collectImages( $anchor );
			if ( empty( $images ) ) {
				continue;
			}

			$this->restructure( $anchor, $images );
		}
	}

	/**
	 * Removes all <br/> elements found anywhere inside $anchor.
	 *
	 * @param DOMElement $anchor
	 * @return void
	 */
	private function removeBrElements( DOMElement $anchor ): void {
		$brNodes = [];
		foreach ( $anchor->getElementsByTagName( 'br' ) as $br ) {
			$brNodes[] = $br;
		}
		foreach ( $brNodes as $br ) {
			$br->parentNode->removeChild( $br );
		}

	}

	/**
	 * Collects all <ac:image> elements anywhere inside $anchor.
	 *
	 * @param DOMElement $anchor
	 * @return DOMElement[]
	 */
	private function collectImages( DOMElement $anchor ): array {
		$images = [];
		foreach ( $anchor->getElementsByTagName( 'image' ) as $image ) {
			$images[] = $image;
		}
		return $images;
	}

	/**
	 * Promotes $images to direct children of $anchor and moves everything
	 * else in $anchor to immediately after it.
	 *
	 * @param DOMElement $anchor
	 * @param DOMElement[] $images
	 * @return void
	 */
	private function restructure( DOMElement $anchor, array $images ): void {
		$parent = $anchor->parentNode;
		$insertBefore = $anchor->nextSibling;

		foreach ( $images as $image ) {
			$image->parentNode->removeChild( $image );
		}

		$directChildren = [];
		foreach ( $anchor->childNodes as $child ) {
			$directChildren[] = $child;
		}
		foreach ( $directChildren as $child ) {
			$anchor->removeChild( $child );
			if ( $insertBefore !== null ) {
				$parent->insertBefore( $child, $insertBefore );
			} else {
				$parent->appendChild( $child );
			}
		}

		foreach ( $images as $image ) {
			$anchor->appendChild( $image );
		}
	}
}
