<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class StructuredMacroInclude extends StructuredMacroProcessorBase {

	/**
	 * @var ConversionDataLookup
	 */
	protected $dataLookup;

	/**
	 * @param ConversionDataLookup $dataLookup
	 */
	public function __construct( ConversionDataLookup $dataLookup ) {
		$this->dataLookup = $dataLookup;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'include';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->getMacroParams( $node );
		// TBD
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @return array
	 */
	private function getMacroParams( $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );

				if ( $paramName === '' ) {
					continue;
				}

				$params[$paramName] = $childNode->nodeValue;
			}
		}

		return $params;
	}
}
