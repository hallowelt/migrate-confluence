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
		$replacementElement = $node->ownerDocument->createElement( 'syntaxhighlight' );
		$replacementElement->appendChild(
			$replacementElement->ownerDocument->createTextNode( '###CONTENT###' )
		);

		$this->processParamElements( $node, $replacementElement );
		/* HTML in syntaxhighlight must not be quoted, therefore we must embed any text verbatim */
		$plainTextContent = $this->processPlainTextBody( $node );
		$replacementSource = str_replace(
			'###CONTENT###',
			$plainTextContent,
			$replacementElement->ownerDocument->saveXML( $replacementElement, LIBXML_NOEMPTYTAG ) );
		$replacementSource .= $plainTextContent !== '' ?
			'' :
			'[[Category:Broken_macro/code/empty]]';

		$node->parentNode->replaceChild( $node->ownerDocument->createTextNode(
			$this->placeholderManager->getPlaceholder( $replacementSource ) ),
			$node );
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
	 * @return string the content
	 */
	private function processPlainTextBody( DOMElement $node ): string {
		$content = '';
		$plaintextEls = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plaintextEls as $plaintextEl ) {
			$content .= $plaintextEl->nodeValue;
		}

		return $content;
	}
}
