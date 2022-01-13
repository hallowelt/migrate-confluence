<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\IStructuredMacroProcessor;

abstract class StructuredMacroProcessorBase implements IStructuredMacroProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'ac', 'some' );
		$xpath->registerNamespace( 'ri', 'thing' );

		// <ac:structured-macro ac:name="column"
		$macros = $xpath->query( './ac:structured-macro' );
		$macroName = $this->getMacroName();
		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === $macroName ) {
				$macroReplacement = $dom->createElement( 'div' );
				$macroReplacement->setAttribute( 'class', "ac-$macroName" );
				$this->macroParams( $macro, $macroReplacement );
				$this->macroBody( $macro, $macroReplacement );
				$macro->parentNode->replaceChild( $macroReplacement, $macro );
			}
		}
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return void
	 */
	private function macroParams( $macro, $macroReplacement ): void {
		// <ac:structured-macro ac:name="width"
		$acParams = $macro->query( './ac:parameter', $macro );
		$params = [];
		foreach ( $acParams as $acParam ) {
			$paramName = $acParam->getAttribute( 'ac:name' );
			$params[$paramName] = $acParam->nodeValue;
		}
		$macroReplacement->setAttribute( 'data-params', json_encode( $params ) );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroReplacement
	 * @return void
	 */
	private function macroBody( $macro, $macroReplacement ): void {
		$body = $macro->query( './ac:rich-text-body', $macro )->item( 0 );
		// Move all content out of <ac::rich-text-body>
		while ( $body->childNodes->length > 0 ) {
			$child = $body->childNodes->item( 0 );
			$macroReplacement->appendChild( $child );
		}
	}
}
