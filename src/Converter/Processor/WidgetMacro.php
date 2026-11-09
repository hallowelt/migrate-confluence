<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 */
class WidgetMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'widget';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-widget" );

		$params = $this->macroParams( $node, $macroReplacement );

		if ( isset( $params[ 'url' ] ) ) {
			// Placeholder prevents pandoc from wrapping the URL in <nowiki>, so that MediaWiki auto-links it
			$macroReplacement->appendChild(
				$this->createTextNode(
					$node->ownerDocument,
					$this->placeholderManager->getPlaceholder( str_replace( '&', '&amp;', $params['url'] ) ),
					__METHOD__
				)
			);
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMElement $macro
	 * @param DOMElement $macroReplacement
	 *
	 * @return array
	 */
	private function macroParams( DOMElement $macro, DOMElement $macroReplacement ): array {
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

			$value = $this->getParamValue( $childNode );

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
	 * @return DOMNode|null
	 */
	private function getAttribute( DOMNode $node, string $name ): ?DOMNode {
		$attributes = $node->attributes;

		if ( $attributes && $attributes->count() > 0 ) {
			for ( $index = 0; $index < $attributes->count(); $index++ ) {
				$attribute = $attributes->item( $index );

				if ( $attribute instanceof DOMNode === false ) {
					continue;
				}

				if ( $attribute->nodeName !== $name ) {
					continue;
				}

				return $attribute;
			}
		}

		return null;
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return string|null
	 */
	private function getParamValue( DOMElement $node ): ?string {
		$value = '';
		$childNodes = $node->childNodes;

		if ( $childNodes->count() > 0 ) {
			for ( $index = 0; $index < $childNodes->count(); $index++ ) {
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
