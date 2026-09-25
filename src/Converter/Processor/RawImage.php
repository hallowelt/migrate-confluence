<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;

/**
 * Handles raw HTML <img> nodes (as opposed to <ac:image>). Its source is in
 * the `src` attribute rather than a child element. MediaWiki does not
 * render an img tag pointing to an external url, so:
 * - with height/width it is replaced by the {{PlainUrlImage}} template
 *   (preserving height/width and, if the <img> is wrapped in a link, the
 *   link target);
 * - without height/width it is replaced by the plain url (stripped of query
 *   params), or, if wrapped in a link, by a link with the url as its text.
 * Anything else (e.g. a relative/internal src) is left untouched.
 */
class RawImage extends ImageProcessorBase {

	/**
	 * Inline elements that may sit between an <a> and the <img> it encloses.
	 */
	private const INLINE_WRAPPERS = [ 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'code' ];

	public function process( DOMDocument $dom ): void {
		$imgNodes = [];
		foreach ( $dom->getElementsByTagName( 'img' ) as $imgNode ) {
			$imgNodes[] = $imgNode;
		}

		foreach ( $imgNodes as $imgNode ) {
			$this->doProcessImg( $imgNode );
		}
	}

	private function doProcessImg( DOMElement $node ): void {
		$urlText = $this->getExternalUrlText( $node->getAttribute( 'src' ) );
		if ( $urlText === '' ) {
			// relative/internal src (no scheme/host) -> not handled here, leave as-is
			return;
		}

		$dom = $node->ownerDocument;
		$anchor = $this->getEnclosingExternalAnchor( $node );

		if ( $anchor !== null ) {
			$href = $anchor->getAttribute( 'href' );

			if ( !$this->hasDimensions( $node ) ) {
				$this->replaceAnchorWithTextLink( $anchor, $href, $urlText );

				return;
			}

			$anchor->parentNode->replaceChild(
				$this->createTextNode(
					$dom,
					$this->makePlainUrlImageReplacement( $node, $urlText, $href ),
					__METHOD__
				),
				$anchor
			);

			return;
		}

		$replacementText = $this->hasDimensions( $node )
			? $this->makePlainUrlImageReplacement( $node, $urlText )
			: $urlText;

		$node->parentNode->replaceChild(
			$this->createTextNode( $dom, $replacementText, __METHOD__ ),
			$node
		);
	}

	private function hasDimensions( DOMElement $node ): bool {
		return $node->getAttribute( 'width' ) !== ''
			|| $node->getAttribute( 'height' ) !== '';
	}

	/**
	 * MediaWiki does not render an img tag pointing to an external url.
	 * Wrap the url (and, if present, the link it is enclosed by) in the
	 * {{PlainUrlImage}} template so the wiki side can decide how to render it.
	 */
	private function makePlainUrlImageReplacement( DOMElement $node, string $urlText, string $link = '' ): string {
		$params = [];
		if ( $link !== '' ) {
			$params[] = "link=$link";
		}
		$params[] = "url=$urlText";

		$height = $node->getAttribute( 'height' );
		if ( $height !== '' ) {
			$params[] = "height=$height";
		}

		$width = $node->getAttribute( 'width' );
		if ( $width !== '' ) {
			$params[] = "width=$width";
		}

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			"PlainUrlImage"
		);

		return '{{PlainUrlImage|' . implode( '|', $params ) . '}}';
	}

	/**
	 * Replaces the anchor (including any inline wrappers around the image)
	 * with a fresh <a href="$href">$text</a>.
	 */
	private function replaceAnchorWithTextLink( DOMElement $anchor, string $href, string $text ): void {
		$dom = $anchor->ownerDocument;

		$newAnchor = $dom->createElement( 'a' );
		$newAnchor->setAttribute( 'href', $href );
		$newAnchor->appendChild( $this->createTextNode( $dom, $text, __METHOD__ ) );

		$anchor->parentNode->replaceChild( $newAnchor, $anchor );
	}

	/**
	 * Returns the <a> with an absolute href enclosing the image, looking
	 * through inline wrappers like <span> or <strong>. Returns null if there
	 * is none.
	 */
	private function getEnclosingExternalAnchor( DOMElement $node ): ?DOMElement {
		$current = $node->parentNode;
		while ( $current instanceof DOMElement ) {
			if ( $current->nodeName === 'a' ) {
				if ( !$current->hasAttribute( 'href' ) ) {
					return null;
				}
				$parsedUrl = parse_url( $current->getAttribute( 'href' ) );

				return isset( $parsedUrl['scheme'] ) ? $current : null;
			}
			if ( !in_array( $current->nodeName, self::INLINE_WRAPPERS, true ) ) {
				return null;
			}
			$current = $current->parentNode;
		}

		return null;
	}

}
