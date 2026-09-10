<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMException;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 *
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\CodeMacro
 */
class CodeMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

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
		$macroReplacement = $node->ownerDocument->createElement( 'syntaxhighlight' );

		$this->processParamElements( $node, $macroReplacement );
		$brokenCat = $this->processPlainTextBody( $node, $macroReplacement ) ?
			'' :
			'[[Category:Broken_macro/code/empty]]';

		$macroReplacement = $node->ownerDocument->createTextNode(
			$this->placeholderManager->getPlaceholder(
			$macroReplacement->ownerDocument->saveXML( $macroReplacement ) . $brokenCat ) );
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
	 * @return bool if there was any content in the element
	 */
	private function processPlainTextBody( DOMElement $node, DOMElement $replacementNode ): bool {
		$hasPlaintextEls = false;
		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {
			$replacementNode->appendChild(
				$this->createTextNode( $replacementNode->ownerDocument, $plaintextEl->nodeValue, __METHOD__ )
			);
			$hasPlaintextEls = true;
		}

		return $hasPlaintextEls;
	}
}
