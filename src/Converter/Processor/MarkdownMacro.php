<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

class MarkdownMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'markdown';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$brokenMacro = false;

		$markdownContent = '';
		$plainTextBodies = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plainTextBodies as $plainTextBody ) {
			$markdownContent = $plainTextBody->nodeValue;
			break;
		}

		if ( $markdownContent === '' ) {
			$brokenMacro = true;
		}

		$replacement = null;

		if ( !$brokenMacro ) {
			$wrapper = $node->ownerDocument->createElement( 'markdown' );
			$wrapper->appendChild(
				$this->createTextNode( $node->ownerDocument, $markdownContent, __METHOD__ )
			);

			$replacement = $wrapper;
		}

		if ( $brokenMacro ) {
			$replacement = $this->createTextNode(
				$node->ownerDocument,
				$this->getBrokenMacroCategory(),
				__METHOD__
			);
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
