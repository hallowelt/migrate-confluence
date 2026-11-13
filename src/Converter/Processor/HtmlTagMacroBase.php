<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Replaces a macro with a plain HTML element (`TAG`); Pandoc turns it into wikitext later.
 * Macro parameters become HTML attributes.
 */
abstract class HtmlTagMacroBase implements IProcessor {

	public const MACRO_NAME = '';

	public const TAG = '';

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = [];
		foreach ( [ 'structured-macro', 'macro' ] as $tagName ) {
			foreach ( $dom->getElementsByTagName( $tagName ) as $macro ) {
				if ( $macro->getAttribute( 'ac:name' ) === static::MACRO_NAME ) {
					$macros[] = $macro;
				}
			}
		}

		foreach ( $macros as $macro ) {
			$replacement = $this->replacement( $macro, $this->getParams( $macro ) );
			$macro->parentNode->replaceChild( $replacement, $macro );
		}
	}

	/**
	 * @param DOMElement $macro
	 * @param array<string, string> $params
	 * @return DOMNode
	 */
	protected function replacement( DOMElement $macro, array $params ): DOMNode {
		$element = $macro->ownerDocument->createElement( static::TAG );
		foreach ( $this->mapParams( $params ) as $name => $value ) {
			$element->setAttribute( $name, $value );
		}
		$this->appendBody( $macro, $element );
		return $element;
	}

	/**
	 * Translates macro parameters into HTML attributes.
	 *
	 * @param array<string, string> $params
	 * @return array<string, string>
	 */
	protected function mapParams( array $params ): array {
		$attributes = [];
		$unmappable = [];
		foreach ( $params as $name => $value ) {
			if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $name ) ) {
				$attributes[$name] = $value;
			} else {
				$unmappable[$name] = $value;
			}
		}
		// Unnamed or oddly named parameters cannot be attributes, but must not get lost
		if ( $unmappable ) {
			$attributes['data-params'] = json_encode( $unmappable );
		}
		return $attributes;
	}

	/**
	 * @param array<string, string> $attributes
	 * @param string $css
	 * @return array<string, string>
	 */
	protected function addStyle( array $attributes, string $css ): array {
		$existing = rtrim( trim( $attributes['style'] ?? '' ), ';' );
		$attributes['style'] = trim( ( $existing !== '' ? $existing . '; ' : '' ) . $css );
		return $attributes;
	}

	/**
	 * @param DOMElement $macro
	 * @return array<string, string>
	 */
	protected function getParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ac:parameter' ) {
				$params[$child->getAttribute( 'ac:name' )] = $child->nodeValue;
			}
		}
		return $params;
	}

	/**
	 * Moves the macro body into the replacement, so nested macros stay processable.
	 *
	 * @param DOMElement $macro
	 * @param DOMElement $target
	 * @return void
	 */
	protected function appendBody( DOMElement $macro, DOMElement $target ): void {
		foreach ( iterator_to_array( $macro->childNodes ) as $child ) {
			if ( $child->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $child->childNodes ) as $bodyNode ) {
					$target->appendChild( $bodyNode );
				}
			} elseif ( $child->nodeName === 'ac:plain-text-body' ) {
				$target->appendChild( $macro->ownerDocument->createTextNode( $child->textContent ) );
			}
		}
	}
}
