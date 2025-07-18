<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;

class StructuredMacroGliffy extends StructuredMacroProcessorBase {

	/**
	 * @var ConversionDataLookup
	 */
	protected $dataLookup;

	/**
	 * @var ConversionDataWriter
	 */
	protected $conversionDataWriter;

	/**
	 * @var int
	 */
	protected $currentSpaceId;

	/**
	 * @var string
	 */
	protected $rawPageTitle;

	/**
	 * @var DataBucktes
	 */
	private $dataBuckets;

	/**
	 * @var bool
	 */
	protected $nsFileRepoCompat = false;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param ConversionDataWriter $conversionDataWriter
	 * @param integer $currentSpaceId
	 * @param string $rawPageTitle
	 * @param DataBuckets $dataBuckets
	 * @param boolean $nsFileRepoCompat
	 */
	public function __construct( ConversionDataLookup $dataLookup, ConversionDataWriter $conversionDataWriter,
		int $currentSpaceId, string $rawPageTitle,  DataBuckets &$dataBuckets, bool $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->conversionDataWriter = $conversionDataWriter;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->dataBuckets = $dataBuckets;
		$this->nsFileRepoCompat = $nsFileRepoCompat;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'gliffy';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->getMacroParams( $node );

		if ( isset( $params['name'] ) ) {
			$paramsString = $this->makeParamsString( $params );

			// Gliffy will be used as Drawio image
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( "{{Gliffy$paramsString}}" ),
				$node
			);
		}
	}

	/**
	 * @param array $params
	 * @return string
	 */
	private function makeParamsString( array $params ): string {
		$paramsString = '';

		if ( isset( $params['name'] ) && $params['name'] !== '' ) {
			$name = $params['name'];
			if ( strtolower( substr( $name, strlen( $name ) - 4 ) )  !== '.png' ) {
				$name .= '.png';
			}
			$filename = $this->getFilename( $name );
			if ( $filename !== '' ) {
				$params['name'] = $filename;
				$this->dataBuckets->addData( 'gliffy-map', $params['name'], $filename, true, true );
			}
		} else {
			return '';
		}

		if ( isset( $params['macroId'] ) ) {
			unset( $params['macroId'] );
		}

		foreach ( $params as $key => $value ) {
			$paramsString .= "|$key=$value\n";
		}

		return $paramsString;
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
	 * @param string $diagramName
	 * @return string
	 */
	private function getFilename( string $diagramName ): string {
		$spaceId = $this->currentSpaceId;
		$rawPageTitle = basename( $this->rawPageTitle );


		// TODO get correct key !!



		
		$confluenceFileKey = "$spaceId---$rawPageTitle---" . str_replace( ' ', '_', $diagramName );
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
