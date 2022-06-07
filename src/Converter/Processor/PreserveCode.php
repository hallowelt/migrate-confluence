<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Unfortunately `pandoc` eats <syntaxhighlight> tags.
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreCode
 */
class PreserveCode implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );

		// Collect all DOMElements in a non-live list
		$actualMacros = [];
		foreach ( $macros as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );
			if ( $macroName !== 'code' ) {
				continue;
			}
			$actualMacros[] = $macro;
		}

		foreach ( $actualMacros as $actualMacro ) {
			$preEl = $dom->createElement( 'pre' );
			$preEl->setAttribute( 'class', 'PRESERVESYNTAXHIGHLIGHT' );

			/** @var DOMElement $actualMacro */
			foreach ( $actualMacro->childNodes as $child ) {
				$paramEls = $child->getElementsByTagName( 'parameter' );
				foreach ( $paramEls as $paramEl ) {
					$paramName = $paramEl->getAttribute( 'name' );
					if ( $paramName === 'language' ) {
						$preEl->setAttribute( 'lang', $paramEl->nodeValue );
					}
					if ( $paramName === 'collapse' ) {
						$preEl->setAttribute( 'data-collapse', $paramEl->nodeValue );
					}
					if ( $paramName === 'title' ) {
						$headingEl = $dom->createElement( 'h6' );
						$headingEl->appendChild( $dom->createTextNode( $paramEl->nodeValue ) );
						$actualMacro->parentNode->insertBefore( $headingEl, $actualMacro );
					}
				}

				$plaintextEls = $child->getElementsByTagName( 'plain-text-body' );
				foreach ( $plaintextEls as $plaintextEl ) {
					$preEl->appendChild( $plaintextEl );
				}

				if ( $plaintextEls->count() === 0 ) {
					$preEl->appendChild(
						$dom->createTextNode( '[[Category:Broken_macro/code/empty]]' )
					);
				}
			}
			$dom->replaceChild( $preEl, $actualMacro );
		}
	}
}
