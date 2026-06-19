<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;

class GliffyMacro extends StructuredMacroProcessorBase {

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param PipeToDB $pipeToDB
	 */
	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpaceId,
		private string $rawPageTitle,
		private PipeToDB $pipeToDB
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
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );

		if ( isset( $params['name'] ) ) {
			$paramsString = $this->makeParamsString( $params );

			$brokenMacro = '';
			if ( $paramsString === '' ) {
				$brokenMacro = $this->getCategoryBrokenMacro( "gliffy" );
			}

			// Gliffy will be used as Drawio image
			$node->parentNode->replaceChild(
				$this->createTextNode(
					$node->ownerDocument,
					"{{Gliffy$paramsString}}$brokenMacro",
					__METHOD__
				),
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

			$filename = $this->dataLookup->getWikiFileTitleFromSpaceId(
				$this->currentSpaceId,
				$this->rawPageTitle,
				$name
			) ?? '';

			if ( $filename === '' ) {
				$fallbackExtensions = [ '.SVG', '.PNG', '.svg', '.png' ];
				foreach ( $fallbackExtensions as $ext ) {
					$name = $params['name'] . $ext;

					$filename = $this->dataLookup->getWikiFileTitleFromSpaceId(
						$this->currentSpaceId,
						$this->rawPageTitle,
						$name
					) ?? '';

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

		$this->pipeToDB->send(
			'addGliffy',
			$this->currentSpaceId,
			$this->rawPageTitle,
			$name,
			$filename
		);

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
	 * @param DOMElement $macro
	 *
	 * @return array
	 */
	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
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
