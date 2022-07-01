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

		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {
			$macroReplacement->appendChild( $plaintextEl );
		}

		if ( $plaintextEls->count() === 0 ) {
			$macroReplacement->appendChild(
				$node->ownerDocument->createTextNode( '[[Category:Broken_macro/code/empty]]' )
			);
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $replacementNode
	 * @return void
	 */
	private function processParamElements( $node, $replacementNode ): void {

		// GO ON HERE



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
				$replacementNode->insertBefore( $headingEl, $replacementNode );
			}
		}
	}
}
