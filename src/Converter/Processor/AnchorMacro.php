<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class AnchorMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'anchor';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$anchorName = '';
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === '' ) {
				$anchorName = trim( $childNode->nodeValue );
				break;
			}
		}

		if ( $anchorName === '' ) {
			$replacement = $this->createTextNode(
				$node->ownerDocument,
				$this->getBrokenMacroCategory(),
				__METHOD__
			);
		} else {
			$replacement = $node->ownerDocument->createElement( 'span' );
			$replacement->setAttribute( 'id', $anchorName );
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
