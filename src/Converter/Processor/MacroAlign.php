<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class MacroAlign implements IProcessor {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'align';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macrosTags = $dom->getElementsByTagName( 'macro' );

		$macros = [];
		foreach ( $macrosTags as $macrosTag ) {
			$macros[] = $macrosTag;
		}

		$macroName = $this->getMacroName();
		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === $macroName ) {
				$this->doProcessMacro( $macro );
			}
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$macroName = $node->getAttribute( 'ac:name' );

		$macroReplacement = $node->ownerDocument->createElement( 'div' );

		$macroReplacement->setAttribute( 'class', "ac-macro-$macroName" );

		$macroParams = $this->getMacroParams( $node, $macroReplacement );
		if ( !empty( $macroParams ) ) {
			$macroReplacement->setAttribute( 'data-params', json_encode( $macroParams ) );
		}

		if ( isset( $macroParams['align'] ) ) {
			$style = 'text-align: ' . $macroParams['align'] . ';';
			$macroReplacement->setAttribute( 'style', $style );
		}
		
		$this->macroBody( $node, $macroReplacement );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return array
	 */
	private function getMacroParams( $macro, $macroReplacement ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				$params[$paramName] = $childNode->nodeValue;
			}
		}

		return $params;
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return void
	 */
	private function macroBody( $macro, $macroReplacement ): void {
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( $childNode->childNodes as $node ) {
					$newNode = $node->cloneNode( true );
					$macroReplacement->appendChild( $newNode );
				}
			}
		}
	}
}
