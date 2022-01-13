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
				$macroContainer = $dom->createElement( 'div' );
				$macroContainer->setAttribute( 'class', "ac-$macroName" );
				$this->macroParams( $macro, $macroContainer );
				$this->macroBody( $macro, $macroContainer );
			}
		}
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroContainer
	 * @return void
	 */
	private function macroParams( $macro, $macroContainer ): void {
		// <ac:structured-macro ac:name="width"
		$acParams = $macro->query( './ac:parameter', $macro );
		$params = [];
		foreach ( $acParams as $acParam ) {
			$paramName = $acParam->getAttribute( 'ac:name' );
			$params[$paramName] = $acParam->nodeValue;
		}
		$macroContainer->setAttribute( 'data-params', json_encode( $params ) );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @param DOMElement $macroContainer
	 * @return void
	 */
	private function macroBody( $macro, $macroContainer ): void {
		$body = $macro->query( './ac:rich-text-body', $macro )->item( 0 );
		// Move all content out of <ac::rich-text-body>
		while ( $body->childNodes->length > 0 ) {
			$child = $body->childNodes->item( 0 );
			$macroContainer->appendChild( $child );
		}
	}
}
