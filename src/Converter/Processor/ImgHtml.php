<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;

/**
 * Handles raw HTML <img> nodes (as opposed to <ac:image>). Its source is in
 * the `src` attribute rather than a child element. MediaWiki does not
 * render an img tag pointing to an external url, so:
 * - with height/width it is replaced by the {{ExternalImage}} template
 *   (preserving height/width and, if the <img> is wrapped in a link, the
 *   link target);
 * - without height/width it is replaced by the plain url (stripped of query
 *   params), or, if wrapped in a link, by a link with the url as its text.
 * Anything else (e.g. a relative/internal src) is left untouched.
 */
class ImgHtml extends ImageProcessorBase {

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
		$url = $this->modifyExternalImageUrl( $node->getAttribute( 'src' ) );
		if ( $url === '' ) {
			// relative/internal src (no scheme/host) -> not handled here, leave as-is
			return;
		}

		$dom = $node->ownerDocument;
		$anchor = $this->getEnclosingExternalAnchor( $node );

		if ( $anchor !== null ) {
			$href = $anchor->getAttribute( 'href' );

			if ( !$this->hasDimensions( $node ) ) {
				$this->replaceAnchorWithTextLink( $anchor, $href, $url );

				return;
			}

			$anchor->parentNode->replaceChild(
				$this->createTextNode(
					$dom,
					$this->makeExternalImageReplacement( $node, $url, $href ),
					__METHOD__
				),
				$anchor
			);

			return;
		}

		$replacementText = $this->hasDimensions( $node )
			? $this->makeExternalImageReplacement( $node, $url )
			: $url;

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
	 * {{ExternalImage}} template so the wiki side can decide how to render it.
	 */
	private function makeExternalImageReplacement( DOMElement $node, string $urlText, string $link = '' ): string {
		return $this->buildExternalImageTemplate(
			$this->getExternalImageParams( $node ),
			$urlText,
			$link
		);
	}

	/**
	 * Collects the <img> node's attributes (e.g. height, width, ...) as
	 * "name=value" template params. "src" is excluded, as it is already
	 * carried via the url param. height/width come first for a stable,
	 * predictable param order.
	 */
	private function getExternalImageParams( DOMElement $node ): array {
		$params = [];

		foreach ( [ 'height', 'width' ] as $name ) {
			$value = $node->getAttribute( $name );
			if ( $value !== '' ) {
				$params[$name] = $value;
			}
		}

		foreach ( $node->attributes as $attribute ) {
			$name = $attribute->nodeName;
			if ( $name === 'src' || isset( $params[$name] ) ) {
				continue;
			}

			$params[$name] = $attribute->value;
		}

		return $params;
	}

	/**
	 * Returns the <a> with an absolute href enclosing the image, looking
	 * through inline wrappers like <span> or <strong>. Returns null if there
	 * is none.
	 */
	private function getEnclosingExternalAnchor( DOMElement $node ): ?DOMElement {
		$anchor = $this->findEnclosingAnchor( $node );

		return $this->isExternalAnchor( $anchor ) ? $anchor : null;
	}

}
