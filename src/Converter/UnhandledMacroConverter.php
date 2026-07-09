<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

class UnhandledMacroConverter extends ConversionHelper implements IProcessor {

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
				$this->createTextNode(
					$replacement->ownerDocument,
					'###HTMLCOMMENTOPEN###' . $macro->ownerDocument->saveXML( $macro ) . '###HTMLCOMMENTCLOSE###',
					__METHOD__
				)
			);

			$replacement->appendChild(
				$this->createTextNode(
					$replacement->ownerDocument,
					$this->getCategoryBrokenMacro( $macroName ),
					__METHOD__
				)
			);

			$macro->parentNode->replaceChild( $replacement, $macro );
		}
	}
}
