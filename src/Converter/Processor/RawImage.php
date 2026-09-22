<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;

/**
 * Handles raw HTML <img> nodes (as opposed to <ac:image>). Its source is in
 * the `src` attribute rather than a child element. External images are
 * replaced by their cleaned URL as plain text; anything else (e.g. a
 * relative/internal src) is left untouched.
 */
class RawImage extends ImageProcessorBase {

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

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, $urlText, __METHOD__ ),
			$node
		);
	}

}
