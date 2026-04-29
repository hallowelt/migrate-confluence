<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;

class AnchorLink extends LinkProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$linkNodes = $dom->getElementsByTagName( 'link' );

		$linkNodeList = [];
		foreach ( $linkNodes as $linkNode ) {
			$linkNodeList[] = $linkNode;
		}

		foreach ( $linkNodeList as $linkNode ) {
			if ( !$linkNode instanceof DOMElement ) {
				continue;
			}
			$anchor = $linkNode->getAttribute( 'ac:anchor' );
			if ( $anchor === '' ) {
				continue;
			}
			$this->doProcessLink( $linkNode );
		}
	}

	/**
	 * Not used — process() is overridden to handle anchor-only links directly.
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'anchor';
	}

	/**
	 * @param DOMNode $node The ac:link element carrying the ac:anchor attribute.
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		if ( !$node instanceof DOMElement ) {
			return;
		}

		$anchor = $node->getAttribute( 'ac:anchor' );
		$linkParts = [ '#' . $anchor ];

		$linkBodys = $node->getElementsByTagName( 'link-body' );
		$linkBody = $linkBodys->item( 0 );

		if ( !$linkBody instanceof DOMElement ) {
			$linkBodys = $node->getElementsByTagName( 'plain-text-link-body' );
			$linkBody = $linkBodys->item( 0 );
		}

		if ( $linkBody instanceof DOMElement ) {
			$linkParts[] = trim( $linkBody->nodeValue );
		}

		$replacement = $this->makeLink( $linkParts );

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $replacement ),
			$node
		);
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	public function makeLink( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		if ( count( $linkParts ) > 1 ) {
			return '[[' . implode( '|', $linkParts ) . ']]';
		}

		return '[[' . $linkParts[0] . ']]';
	}
}
