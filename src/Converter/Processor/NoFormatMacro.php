<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 *
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
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'pre' );
		$macroReplacement->setAttribute( 'class', 'noformat' );

		$this->processParamElements( $node, $macroReplacement );
		$this->processPlainTextBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMElement $node
	 * @param DOMElement $replacementNode
	 *
	 * @return void
	 */
	private function processParamElements( DOMElement $node, DOMElement $replacementNode ): void {
		$paramEls = $node->getElementsByTagName( 'parameter' );
		foreach ( $paramEls as $paramEl ) {
			$paramName = $paramEl->getAttribute( 'ac:name' );

			if ( $paramName === 'nopanel' && $paramEl->nodeValue === "true" ) {
				$replacementNode->setAttribute( 'class', 'noformat nopanel' );
			}
		}
	}

	/**
	 * @param DOMElement $node
	 * @param DOMElement $replacementNode
	 *
	 * @return void
	 */
	private function processPlainTextBody( DOMElement $node, DOMElement $replacementNode ): void {
		$hasPlaintextEls = false;
		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {
			$replacementNode->appendChild(
				$this->createTextNode(
					$replacementNode->ownerDocument,
					$plaintextEl->nodeValue,
					__METHOD__
				)
			);
			$hasPlaintextEls = true;
		}

		if ( !$hasPlaintextEls ) {
			$replacementNode->appendChild(
				$this->createTextNode(
					$replacementNode->ownerDocument,
					$this->getBrokenMacroCategory(),
					__METHOD__
				)
			);
		}
	}
}
