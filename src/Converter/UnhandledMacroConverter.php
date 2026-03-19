<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;

class UnhandledMacroConverter {

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function process( DOMDocument $dom ): void {
		$this->handle( 'macro', $dom );
		$this->handle( 'structured-macro', $dom );
	}

	private function handle( string $type, DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( $type );

		$nonLiveList = [];
		foreach ( $macros as $macro ) {
			$nonLiveList[] = $macro;
		}

		foreach ( $nonLiveList as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );

			$replacement = $macro->ownerDocument->createElement( 'div' );
			$replacement->setAttribute( 'class', 'ac-' . $macroName );

			$replacement->appendChild(
				$replacement->ownerDocument->createTextNode(
					'<!--' . $macro->ownerDocument->saveXML( $macro ) . '-->'
				)
			);

			$replacement->appendChild(
				$replacement->ownerDocument->createTextNode(
					"[[Category:Broken_macro/$macroName]]"
				)
			);

			$macro->parentNode->replaceChild( $replacement, $macro );
		}
	}
}
