<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreCode
 */
class PreserveCode extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'code';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'pre' );
		$macroReplacement->setAttribute( 'class', 'PRESERVESYNTAXHIGHLIGHT' );

		$this->processParamElements( $node, $macroReplacement );
		$this->processPlainTextBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $replacementNode
	 * @return void
	 */
	private function processParamElements( $node, $replacementNode ): void {
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
					$replacementNode->ownerDocument->createTextNode( $paramEl->nodeValue )
				);
				$node->parentNode->insertBefore( $headingEl, $node );
			}
		}
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $replacementNode
	 * @return void
	 */
	private function processPlainTextBody( $node, $replacementNode ): void {
		$hasPlaintextEls = false;
		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {

			$code = $plaintextEl->nodeValue;
			$code = base64_encode( $plaintextEl->nodeValue );

			$replacementNode->appendChild(
				$replacementNode->ownerDocument->createTextNode( $code )
			);
			$hasPlaintextEls = true;
		}

		if ( !$hasPlaintextEls ) {
			$replacementNode->setAttribute( 'data-broken-macro', 'Broken_macro/code/empty' );
		}
	}
}
