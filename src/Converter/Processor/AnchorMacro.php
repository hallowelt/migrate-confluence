<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class AnchorMacro implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			if ( $structuredMacro->getAttribute( 'ac:name' ) === 'anchor' ) {
				$macros[] = $structuredMacro;
			}
		}

		foreach ( $macros as $macro ) {
			$anchorName = '';
			foreach ( $macro->childNodes as $childNode ) {
				if ( $childNode->nodeName === 'ac:parameter'
					&& $childNode->getAttribute( 'ac:name' ) === '' ) {
					$anchorName = trim( $childNode->nodeValue );
					break;
				}
			}

			if ( $anchorName === '' ) {
				continue;
			}

			$span = $dom->createElement( 'span' );
			$span->setAttribute( 'id', $anchorName );
			$macro->parentNode->replaceChild( $span, $macro );
		}
	}
}
