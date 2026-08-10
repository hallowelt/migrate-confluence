<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

/**
 * Shared logic for Confluence's inline text-symbol macros (`tm`, `reg-tm`,
 * `copyright`, `sm`, ...): the rich-text body is kept as-is and followed by
 * a fixed symbol, matching Confluence's classic `{tm}Product Name{tm}` ->
 * "Product Name™" rendering. `id`/`class` only produce a wrapping `<span>`
 * when actually present in the source.
 *
 * <ac:structured-macro ac:name="tm" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="id">...</ac:parameter>
 *   <ac:parameter ac:name="class">...</ac:parameter>
 *   <ac:rich-text-body>
 *     <p>Product Name</p>
 *   </ac:rich-text-body>
 * </ac:structured-macro>
 */
abstract class SymbolMacroBase extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	abstract protected function getSymbol(): string;

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->readParams( $node );

		$fragment = $node->ownerDocument->createDocumentFragment();
		foreach ( $node->getElementsByTagName( 'rich-text-body' ) as $richTextBody ) {
			foreach ( iterator_to_array( $richTextBody->childNodes ) as $bodyChild ) {
				$fragment->appendChild( $bodyChild->cloneNode( true ) );
			}
		}
		$fragment->appendChild(
			$this->createTextNode( $node->ownerDocument, $this->getSymbol(), __METHOD__ )
		);

		if ( empty( $params['id'] ) && empty( $params['class'] ) ) {
			$node->parentNode->replaceChild( $fragment, $node );
			return;
		}

		$macroReplacement = $node->ownerDocument->createElement( 'span' );
		if ( !empty( $params['id'] ) ) {
			$macroReplacement->setAttribute( 'id', $params['id'] );
		}
		if ( !empty( $params['class'] ) ) {
			$macroReplacement->setAttribute( 'class', $params['class'] );
		}
		$macroReplacement->appendChild( $fragment );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMElement $node
	 * @return array
	 */
	private function readParams( DOMElement $node ): array {
		$params = [];
		foreach ( $node->getElementsByTagName( 'parameter' ) as $paramEl ) {
			$paramName = $paramEl->getAttribute( 'ac:name' );
			if ( $paramName === '' ) {
				continue;
			}
			$params[$paramName] = $paramEl->nodeValue;
		}
		return $params;
	}
}
