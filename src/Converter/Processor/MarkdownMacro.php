<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

class MarkdownMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'markdown';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
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
				$node->ownerDocument->createTextNode( $markdownContent )
			);

			$replacement = $wrapper;
		}

		if ( $brokenMacro ) {
			$replacement = $node->ownerDocument->createTextNode(
				$this->getBrokenMacroCategroy()
			);
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
