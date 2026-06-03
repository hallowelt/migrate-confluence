<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class GliffyMacro extends StructuredMacroProcessorBase {

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpaceId,
		private string $rawPageTitle
	) {
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'gliffy';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$params = $this->getMacroParams( $node );

		if ( isset( $params['name'] ) ) {
			$paramsString = $this->makeParamsString( $params );

			$brokenMacro = '';
			if ( $paramsString === '' ) {
				$brokenMacro = "[[Category:Broken_macro/gliffy]]";
			}

			// Gliffy will be used as Drawio image
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( "{{Gliffy$paramsString}}$brokenMacro" ),
				$node
			);
		}
	}

	/**
	 * @param array $params
	 * @return string
	 */
	private function makeParamsString( array $params ): string {
		if ( isset( $params['name'] ) && $params['name'] !== '' ) {
			$name = $params['name'];

			$extension = substr( $name, strlen( $name ) - 4 );

			$validExtensions = [ '.SVG', '.PNG', '.svg', '.png' ];
			if ( !in_array( $extension, $validExtensions, true ) ) {
				$name .= '.png';
			}

			$filename = $this->dataLookup->getTargetFileTitleFromSpaceId(
				$this->currentSpaceId,
				$this->rawPageTitle,
				$name
			);

			if ( $filename === '' ) {
				$fallbackExtensions = [ '.SVG', '.PNG', '.svg', '.png' ];
				foreach ( $fallbackExtensions as $ext ) {
					$name = $params['name'] . $ext;

					$filename = $this->dataLookup->getTargetFileTitleFromSpaceId(
						$this->currentSpaceId,
						$this->rawPageTitle,
						$name
					);

					if ( $filename !== '' ) {
						break;
					}
				}
			}

			if ( $filename !== '' ) {
				$params['name'] = $filename;
			}
		} else {
			return '';
		}

		if ( isset( $params['macroId'] ) ) {
			unset( $params['macroId'] );
		}

		$paramsString = '';
		foreach ( $params as $key => $value ) {
			$paramsString .= "|$key=$value\n";
		}

		return $paramsString;
	}

	/**
	 * @param DOMNode $macro
	 *
	 * @return array
	 */
	private function getMacroParams( DOMNode $macro ): array {
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
