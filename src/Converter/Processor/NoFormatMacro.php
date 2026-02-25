<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreNoFormat
 */
class NoFormatMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'noformat';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'pre' );
		$macroReplacement->setAttribute( 'class', 'noformat' );

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

			if ( $paramName === 'nopanel' && $paramEl->nodeValue === "true" ) {
				$replacementNode->setAttribute( 'class', 'noformat nopanel' );
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
			$replacementNode->appendChild(
				$replacementNode->ownerDocument->createTextNode( $plaintextEl->nodeValue )
			);
			$hasPlaintextEls = true;
		}

		if ( !$hasPlaintextEls ) {
			$replacementNode->appendChild(
				$node->ownerDocument->createTextNode( $this->getBrokenMacroCategroy() )
			);
		}
	}
}
