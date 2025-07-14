<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMAttr;
use DOMNode;

/**
 */
class Widget extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'widget';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-widget" );

		$params = $this->macroParams( $node, $macroReplacement );

		if ( isset( $params[ 'url' ] ) ) {
			$macroReplacement->nodeValue = $params['url'];
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return void
	 */
	private function macroParams( $macro, $macroReplacement ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}
			$attrName = $this->getAttribute( $childNode, 'ac:name' );
			if ( !$attrName ) {
				continue;
			}

			$name = $attrName->nodeValue;

			$value = $this->getParamValue( $childNode, 'ri:url');

			$params[$name] = $value;
		}

		if ( !empty( $params ) ) {
			$macroReplacement->setAttribute( 'data-params', json_encode( $params ) );
		}

		return $params;
	}

	/**
	 * @param DOMNode $node
	 * @param string $name
	 * @return ?DOMAttr
	 */
	private function getAttribute( DOMNode $node, string $name ): ?DOMAttr {
		$attributes = $node->attributes;

		if ( $attributes && $attributes->count() > 0 ) {
			for ( $index = 0; $index < $attributes->count(); $index++ ) {
				$attribute = $attributes->item( $index );

				if ( $attribute->nodeName !== $name ) {
					continue;
				}

				return $attribute;
			}
		}

		return null;
	}

	/**
	 * @param DOMNode $node
	 * @param string $name
	 * @return void
	 */
	private function getParamValue( DOMNode $node, string $name ) {
		$value = '';
		$childNodes = $node->childNodes;

		if ( $childNodes->count() > 0 ) {
			for( $index = 0; $index < $childNodes->count(); $index++ ) {
				$child = $childNodes->item( $index );
				$attrName = $this->getAttribute( $child, 'ri:value' );
				if ( !$attrName ) {
					continue;
				}

				$value = $attrName->nodeValue;
			}
		}
		
		if ( $value === '' ) {
			$value = $node->nodeValue;
		}

		return $value;
	}
}
