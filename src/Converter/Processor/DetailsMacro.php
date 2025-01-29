<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * <ac:structured-macro ac:name="details" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="id">control</ac:parameter>
 *     <ac:rich-text-body>
 *       <h3>Control details</h3>
 *       <table class="wrapped">
 *     </ac:rich-text-body>
 *     <ac:rich-text-body>
 * 	     <h3>There may be multiple rich texts</h3>
 *     </ac:rich-text-body>
 *     ...
 * </ac:structured-macro>
 */
class DetailsMacro implements IProcessor {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'details';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );
		$requiredMacroName = $this->getMacroName();

		// Collect all DOMElements in a non-live list
		$actualMacros = [];
		foreach ( $macros as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );
			if ( $macroName !== $requiredMacroName ) {
				continue;
			}
			$actualMacros[] = $macro;
		}

		foreach ( $actualMacros as $actualMacro ) {
			$parentNode = $actualMacro->parentNode;

			$detailsDiv = $dom->createElement( 'div' );
			$detailsDiv->setAttribute( 'class', 'details' );

			// Extract scalar parameters, store them as data attributes of 'div'
			$parameterEls = $actualMacro->getElementsByTagName( 'parameter' );
			foreach ( $parameterEls as $parameterEl ) {
				$paramName = $parameterEl->getAttribute( 'ac:name' );
				$paramValue = $parameterEl->nodeValue;

				$detailsDiv->setAttribute( "data-$paramName", $paramValue );
			}

			// Extract rich text bodies
			/** @var DOMNodeList $richTextBodies */
			$richTextBodies = $actualMacro->getElementsByTagName( 'rich-text-body' );
			$richTextBodyEls = [];
			foreach ( $richTextBodies as $richTextBody ) {
				$richTextBodyEls[] = $richTextBody;
			}

			if ( !empty( $richTextBodyEls ) ) {
				foreach ( $richTextBodyEls as $richTextBodyEl ) {
					// For some odd reason, iterating `$richTextBodyEl->childNodes` directly
					// will give children of `$dom->firstChild`.
					// Using `iterator_to_array` as an workaround here.
					$childNodes = iterator_to_array( $richTextBodyEl->childNodes );
					foreach ( $childNodes as $richTextBodyChildEl ) {
						if ( $richTextBodyChildEl === $actualMacro ) {
							continue;
						}
						$detailsDiv->appendChild( $richTextBodyChildEl );
					}
				}
			}

			$parentNode->insertBefore( $detailsDiv, $actualMacro );

			$parentNode->removeChild( $actualMacro );
		}
	}
}
