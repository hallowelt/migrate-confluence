<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\dom;

use DOMDocument;
use DOMElement;
use DOMText;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;

/**
 * Confluence often wraps nested lists in an <li style="list-style-type: none;">
 * to achieve indentation without a visible bullet. Pandoc naively treats that
 * invisible <li> as a real list level, which causes the first inner item to get
 * a combined "* **" prefix that MediaWiki interprets as bold markup.
 *
 * This preprocessor removes the invisible wrapper <li> and promotes its
 * inner <li> children directly into the surrounding list, so the nesting
 * depth seen by Pandoc matches the visual depth in Confluence.
 */
class FlattenListItemWithNoStyle implements IDomPreprocessor {

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function preprocess( DOMDocument $dom ): void {
		$candidates = [];
		foreach ( $dom->getElementsByTagName( 'li' ) as $li ) {
			if ( $li instanceof DOMElement && $this->hasListStyleTypeNone( $li ) ) {
				$candidates[] = $li;
			}
		}

		// Process deepest elements first so multi-level nesting is resolved
		// correctly in a single pass.
		$candidates = array_reverse( $candidates );

		foreach ( $candidates as $li ) {
			$this->flattenItem( $li );
		}
	}

	/**
	 * @param DOMElement $li
	 * @return bool
	 */
	private function hasListStyleTypeNone( DOMElement $li ): bool {
		$style = $li->getAttribute( 'style' );
		// Match both "list-style-type: none" and "list-style-type:none"
		return (bool)preg_match( '/list-style-type\s*:\s*none/', $style );
	}

	/**
	 * Replaces the given <li> in its parent list by the <li> children of any
	 * nested <ul>/<ol> it contains. Whitespace-only text nodes are discarded.
	 * Non-list child content is wrapped in a fresh <li>.
	 *
	 * @param DOMElement $li
	 * @return void
	 */
	private function flattenItem( DOMElement $li ): void {
		$parent = $li->parentNode;
		if ( !$parent instanceof DOMElement ) {
			return;
		}

		// Snapshot children to avoid mutation issues during iteration
		$children = [];
		foreach ( $li->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( $child instanceof DOMElement && in_array( $child->nodeName, [ 'ul', 'ol' ] ) ) {
				// Hoist the inner list's direct children into the outer list
				$innerChildren = [];
				foreach ( $child->childNodes as $innerChild ) {
					$innerChildren[] = $innerChild;
				}
				foreach ( $innerChildren as $innerChild ) {
					$parent->insertBefore( $innerChild, $li );
				}
			} elseif ( $child instanceof DOMText && trim( $child->textContent ) === '' ) {
				// Discard whitespace-only text nodes
			} else {
				// Wrap other non-empty content in a new <li>
				$newLi = $li->ownerDocument->createElement( 'li' );
				$newLi->appendChild( $child );
				$parent->insertBefore( $newLi, $li );
			}
		}

		$parent->removeChild( $li );
	}
}
