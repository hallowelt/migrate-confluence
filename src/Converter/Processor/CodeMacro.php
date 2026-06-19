<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMException;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 *
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\CodeMacro
 */
class CodeMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'code';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', 'PRESERVESYNTAXHIGHLIGHT' );

		$this->processParamElements( $node, $macroReplacement );
		$this->processPlainTextBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMElement $node
	 * @param DOMElement $replacementNode
	 *
	 * @return void
	 * @throws DOMException
	 */
	private function processParamElements( DOMElement $node, DOMElement $replacementNode ): void {
		$paramEls = $node->getElementsByTagName( 'parameter' );
		foreach ( $paramEls as $paramEl ) {
			$paramName = $paramEl->getAttribute( 'ac:name' );

			if ( $paramName === 'language' ) {
				$replacementNode->setAttribute( 'lang', $paramEl->nodeValue );
			}

			if ( $paramName === 'collapse' ) {
				$replacementNode->setAttribute( 'data-collapse', $paramEl->nodeValue );
			}

			if ( $paramName === 'title' ) {
				$headingEl = $replacementNode->ownerDocument->createElement( 'h6' );
				$headingEl->appendChild(
					$this->createTextNode(
						$replacementNode->ownerDocument,
						$paramEl->nodeValue,
						__METHOD__
					)
				);
				$node->parentNode->insertBefore( $headingEl, $node );
			}
		}
	}

	/**
	 * @param DOMElement $node
	 * @param DOMElement $replacementNode
	 * @return void
	 */
	private function processPlainTextBody( DOMElement $node, DOMElement $replacementNode ): void {
		$hasPlaintextEls = false;
		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {

			$code = base64_encode( $plaintextEl->nodeValue );

			$replacementNode->appendChild(
				$this->createTextNode( $replacementNode->ownerDocument, $code, __METHOD__ )
			);
			$hasPlaintextEls = true;
		}

		if ( !$hasPlaintextEls ) {
			$replacementNode->setAttribute( 'data-broken-macro', 'Broken_macro/code/empty' );
		}
	}
}
