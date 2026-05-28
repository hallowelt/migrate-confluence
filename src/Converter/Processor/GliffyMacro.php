<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;

class GliffyMacro extends StructuredMacroProcessorBase {

	/**
	 * @var DBConversionDataLookup
	 */
	protected DBConversionDataLookup $dataLookup;

	/**
	 * @var ConversionDataWriter
	 */
	protected ConversionDataWriter $conversionDataWriter;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	/**
	 * @var string
	 */
	protected string $rawPageTitle;

	/**
	 * @var PipeToDB
	 */
	private PipeToDB $pipeToDB;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param ConversionDataWriter $conversionDataWriter
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param PipeToDB $pipeToDB
	 */
	public function __construct( DBConversionDataLookup $dataLookup, ConversionDataWriter $conversionDataWriter,
		int $currentSpaceId, string $rawPageTitle, PipeToDB $pipeToDB ) {
		$this->dataLookup = $dataLookup;
		$this->conversionDataWriter = $conversionDataWriter;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->pipeToDB = $pipeToDB;
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
