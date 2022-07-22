<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class StructuredMacroToc implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			$macros[] = $structuredMacro;
		}

		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === 'toc' ) {
				$this->doProcessMacro( $macro );
			}
		}
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function doProcessMacro( DOMElement $node ): void {
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( "\n__TOC__\n###BREAK###" ),
			$node
		);
	}
}
