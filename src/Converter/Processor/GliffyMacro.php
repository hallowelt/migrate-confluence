<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReader;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;

class GliffyMacro extends StructuredMacroProcessorBase {

	/**
	 * @param IConverterDataReader $reader
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param IConverterDataWriter $dataWriter
	 */
	public function __construct(
		private IConverterDataReader $reader,
		private int $currentSpaceId,
		private string $rawPageTitle,
		private IConverterDataWriter $dataWriter
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

			$filename = $this->reader->getWikiFileTitleFromSpaceId(
				$this->currentSpaceId,
				$this->rawPageTitle,
				$name
			) ?? '';

			if ( $filename === '' ) {
				$fallbackExtensions = [ '.SVG', '.PNG', '.svg', '.png' ];
				foreach ( $fallbackExtensions as $ext ) {
					$name = $params['name'] . $ext;

					$filename = $this->reader->getWikiFileTitleFromSpaceId(
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

		$this->dataWriter->addGliffy(
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
