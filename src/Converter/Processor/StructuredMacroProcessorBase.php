<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

abstract class StructuredMacroProcessorBase implements IProcessor {

	/**
	 *
	 * @return string
	 */
	abstract protected function getMacroName(): string;

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macroName = $this->getMacroName();

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'ac', 'some' );
		$xpath->registerNamespace( 'ri', 'thing' );

		// <ac:structured-macro ac:name="column"
		$macros = $xpath->query( '//ac:structured-macro' );
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
		$macroReplacement->setAttribute( 'class', "ac-$macroName" );
		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return void
	 */
	private function macroParams( $macro, $macroReplacement ): void {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );

				if ( !in_array( $paramName, [ 'ac', 'ri' ] ) ) {
					$params[$paramName] = $childNode->nodeValue;
				}
			}
		}

		if ( !empty( $params ) ) {
			$macroReplacement->setAttribute( 'data-params', json_encode( $params ) );
		}
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
