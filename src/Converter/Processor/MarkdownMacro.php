<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use Michelf\MarkdownExtra;

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
			$html = $this->convertMarkdownToHtml( $markdownContent );
			if ( $html === '' ) {
				$brokenMacro = true;
			} else {
				$wrapper = $node->ownerDocument->createElement( 'div' );
				$wrapper->setAttribute( 'class', 'ac-markdown' );

				$fragment = $node->ownerDocument->createDocumentFragment();
				$fragment->appendXML( $html );
				$wrapper->appendChild( $fragment );

				$replacement = $wrapper;
			}
		}

		if ( $brokenMacro ) {
			$replacement = $node->ownerDocument->createTextNode(
				$this->getBrokenMacroCategroy()
			);
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}

	/**
	 * @param string $markdown
	 * @return string
	 */
	private function convertMarkdownToHtml( string $markdown ): string {
		return MarkdownExtra::defaultTransform( $markdown );
	}
}
