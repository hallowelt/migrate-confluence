<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

abstract class MacroProcessorBase extends ConversionHelper implements IProcessor {

	public const MACRO_NAME = '';

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			$macros[] = $structuredMacro;
		}

		$macroName = static::MACRO_NAME;

		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === $macroName ) {
				$this->doProcessMacro( $macro );
			}
		}
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return void
	 * @throws DOMException
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroName = $node->getAttribute( 'ac:name' );

		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-$macroName" );
		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMElement $macro
	 *
	 * @return array
	 */
	protected function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				if ( $childNode instanceof DOMElement === false ) {
					continue;
				}
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === '' ) {
					continue;
				}
				$params[$paramName] = $childNode->nodeValue;
			}
		}
		return $params;
	}

	/**
	 *
	 * @param DOMElement $macro
	 * @param DOMElement $macroReplacement
	 *
	 * @return void
	 */
	protected function macroParams( DOMElement $macro, DOMElement $macroReplacement ): void {
		$params = $this->getMacroParams( $macro );

		if ( !empty( $params ) ) {
			$macroReplacement->setAttribute( 'data-params', json_encode( $params ) );
		}
	}

	/**
	 * @param DOMElement $macro
	 * @param DOMElement $macroReplacement
	 *
	 * @return void
	 */
	protected function macroBody( DOMElement $macro, DOMElement $macroReplacement ): void {
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( $childNode->childNodes as $node ) {
					$newNode = $node->cloneNode( true );
					$macroReplacement->appendChild( $newNode );
				}
			}
		}
	}

	/**
	 * @return string
	 */
	protected function getBrokenMacroCategory(): string {
		$macroName = static::MACRO_NAME;
		return $this->getCategoryBrokenMacro( $macroName );
	}
}
