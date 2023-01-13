<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class StructuredMacroDrawIO extends StructuredMacroProcessorBase {

	/**
	 * @var ConversionDataLookup
	 */
	protected $dataLookup;

	/**
	 * @var int
	 */
	protected $currentSpaceId;

	/**
	 * @var string
	 */
	protected $rawPageTitle;

	/**
	 * @var boolean
	 */
	protected $nsFileRepoCompat = false;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param bool $nsFileRepoCompat
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle, bool $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->nsFileRepoCompat = $nsFileRepoCompat;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'drawio';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->getMacroParams( $node );

		if ( isset( $params['diagramName'] ) ) {
			$paramsString = $this->makeParamsString( $params );

			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( "{{#drawio:$paramsString}}" ),
				$node
			);
		}
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

	/**
	 * @param array $params
	 * @return string
	 */
	private function makeParamsString( array $params ): string {
		$paramsString = '';

		if ( isset( $params['diagramName'] ) ) {
			$filename = $this->getFilename( $params['diagramName'] );
			$paramsString .= $filename;
		} else {
			return '';
		}

		if ( isset( $params['width'] ) && $params['width'] !== '' ) {
			$width = $params['width'];
			$paramsString .= '|width=' . $width . 'px';
		}

		return $paramsString;
	}

	/**
	 * @param string $diagramName
	 * @return string
	 */
	private function getFilename( string $diagramName ): string {
		$spaceId = $this->currentSpaceId;
		$rawPageTitle = basename( $this->rawPageTitle );

		$confluenceFileKey = "$spaceId---$rawPageTitle---$diagramName";
		$filename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );

		if ( $this->nsFileRepoCompat ) {
			$filenameParts = explode( '_', $filename );

			if ( count( $filenameParts ) > 2 ) {
				$filename = $filenameParts[0];
				array_shift( $filenameParts );
				$filename .= ':' . implode( '_', $filenameParts );
			}
		}

		return $filename;
	}
}
