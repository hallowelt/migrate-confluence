<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

class AnchorMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'anchor';
	}

	/**
	 * @inheritDoc
	 *
	 * @param DOMElement $node
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$anchorName = '';
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement
				&& $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === '' ) {
				$anchorName = trim( $childNode->nodeValue );
				break;
			}
		}

		if ( $anchorName === '' ) {
			$replacement = $node->ownerDocument->createTextNode(
				$this->getBrokenMacroCategory()
			);
		} else {
			$replacement = $node->ownerDocument->createElement( 'span' );
			$replacement->setAttribute( 'id', $anchorName );
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
