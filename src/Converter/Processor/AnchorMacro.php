<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

class AnchorMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'anchor';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$anchorName = '';
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === '' ) {
				$anchorName = trim( $childNode->nodeValue );
				break;
			}
		}

		if ( $anchorName === '' ) {
			$replacement = $node->ownerDocument->createTextNode(
				$this->getBrokenMacroCategroy()
			);
		} else {
			$replacement = $node->ownerDocument->createElement( 'span' );
			$replacement->setAttribute( 'id', $anchorName );
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
