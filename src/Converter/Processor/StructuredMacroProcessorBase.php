<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

abstract class StructuredMacroProcessorBase extends ConversionHelper implements IProcessor {

	/**
	 *
	 * @return string
	 */
	abstract protected function getMacroName(): string;

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			$macros[] = $structuredMacro;
		}

		$macroName = $this->getMacroName();

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
	 *
	 * @param DOMElement $macro
	 * @param DOMElement $macroReplacement
	 *
	 * @return void
	 */
	private function macroParams( DOMElement $macro, DOMElement $macroReplacement ): void {
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
	private function macroBody( DOMElement $macro, DOMElement $macroReplacement ): void {
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
		$macroName = $this->getMacroName();
		return $this->getCategoryBrokenMacro( $macroName );
	}
}
