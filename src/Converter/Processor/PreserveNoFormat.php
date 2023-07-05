<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreNoFormat
 */
class PreserveNoFormat extends StructuredMacroProcessorBase {

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
		$macroReplacement->setAttribute( 'class', 'PRESERVENOFORMAT' );

		$this->processPlainTextBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
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
				$node->ownerDocument->createTextNode( '[[Category:Broken_macro/noformat]]' )
			);
		}
	}
}
