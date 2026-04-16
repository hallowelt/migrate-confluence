<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Handles <a href="external"> elements whose children cannot be expressed as
 * a MediaWiki external link because they contain block-level or otherwise
 * non-translatable elements (e.g. <span>, <br>, nested <ac:image>).
 *
 * MediaWiki external links only support plain inline text as the link body
 * ([url text]). When an <a> element contains any direct-child DOMElement
 * that is NOT an <ac:image>, pandoc cannot produce valid wikitext for it.
 *
 * This processor runs before the Image processor and transforms:
 *
 *   <a href="url"><span>...<ac:image/>...</span><br/><span>text</span></a>
 *
 * into:
 *
 *   url<span>...<ac:image/>...</span><br/><span>text</span>
 *
 * The bare URL is emitted as a text node so that pandoc passes it through
 * unchanged; MediaWiki then renders it as a numbered auto-link.
 * All former children are placed immediately after the URL text node so they
 * continue to be processed by subsequent processors (Image, etc.) and pandoc.
 *
 * Links whose only element child is <ac:image> are left untouched so the
 * Image processor can handle the image-in-external-link case itself.
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

			$href = $anchor->getAttribute( 'href' );
			if ( $href === '' ) {
				continue;
			}

			$parsedUrl = parse_url( $href );
			if ( !isset( $parsedUrl['scheme'] ) ) {
				// Internal / relative link — leave for pandoc
				continue;
			}

			if ( !$this->isComplexLink( $anchor ) ) {
				continue;
			}

			$this->extractChildren( $anchor, $href, $dom );
		}
	}

	/**
	 * A link is "complex" when it has at least one direct DOMElement child
	 * that is not an <ac:image>. Plain-text children and links whose sole
	 * element child is <ac:image> are left for the Image processor.
	 *
	 * @param DOMElement $anchor
	 * @return bool
	 */
	private function isComplexLink( DOMElement $anchor ): bool {
		foreach ( $anchor->childNodes as $child ) {
			if ( !$child instanceof DOMElement ) {
				continue;
			}
			if ( $child->localName !== 'image' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Move all children of $anchor to immediately after it, then replace
	 * the anchor with a plain text node containing the href URL.
	 *
	 * @param DOMElement $anchor
	 * @param string $href
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractChildren( DOMElement $anchor, string $href, DOMDocument $dom ): void {
		$parent = $anchor->parentNode;
		$insertBefore = $anchor->nextSibling;

		$children = [];
		foreach ( $anchor->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			$anchor->removeChild( $child );
			if ( $insertBefore !== null ) {
				$parent->insertBefore( $child, $insertBefore );
			} else {
				$parent->appendChild( $child );
			}
		}

		$parent->replaceChild( $dom->createTextNode( $href ), $anchor );
	}
}
