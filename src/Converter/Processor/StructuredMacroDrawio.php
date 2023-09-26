<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;

class StructuredMacroDrawio extends StructuredMacroProcessorBase {

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
	 * @var boolean
	 */
	protected $nsFileRepoCompat = false;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param ConversionDataWriter $conversionDataWriter
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param bool $nsFileRepoCompat
	 */
	public function __construct( ConversionDataLookup $dataLookup, ConversionDataWriter $conversionDataWriter,
		int $currentSpaceId, string $rawPageTitle, bool $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->conversionDataWriter = $conversionDataWriter;
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
				$node->ownerDocument->createTextNode( "{{Drawio$paramsString}}" ),
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

		if ( isset( $params['diagramName'] ) ) {
			$filename = $this->getFilename( $params['diagramName'] );
			$params['diagramName'] = $filename;
		} else {
			return '';
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

		$confluenceFileKey = "$spaceId---$rawPageTitle---$diagramName";
		$filename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );

		$fileextension = $this->getFileExtension( $filename );

		if ( $fileextension === 'unknown' || $fileextension === 'drawio' ) {
			$this->bakeDrawIODataInPNG( $rawPageTitle, $filename, $fileextension, $diagramName );
		}

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

	/**
	 * @param string $filename
	 * @return string
	 */
	private function getFileExtension( string $filename ): string {
		$filenameParts = explode( '.', $filename );
		$fileextension = array_pop( $filenameParts );

		return $fileextension;
	}

	/**
	 * @param string $rawPageTitle
	 * @param string $filename
	 * @param string $fileExtension
	 * @param string $diagramName
	 * @return void
	 */
	private function bakeDrawIODataInPNG(
		string $rawPageTitle,
		string $filename,
		string $fileExtension,
		string $diagramName
	): void {
		$spaceId = $this->currentSpaceId;

		// Diagram file could be not an '.png' image, but just a text file with diagram XML
		// In that case it may have '.drawio' extension, or may not have extension at all
		// Anyway, in case with DrawIO diagram there should be a corresponding '.png' image:
		// *	If there is a diagram file 'diagram' - there should be corresponding 'diagram.png' image
		// *	If there is a diagram file 'diagram.drawio' - there will be 'diagram.drawio.png' image

		// Our goal here is to encode and "bake" diagram XML into corresponding '.png' image
		// Because that's how BlueSpice will process it, it needs just single PNG image with "baked" diagram XML

		$drawIoFileHandler = new DrawIOFileHandler();

		// Need to make sure that file is really DrawIO file with diagram data
		$drawIoFile = null;
		$fileContent = $this->dataLookup->getConfluenceFileContent( $filename );

		if ( $drawIoFileHandler->isDrawIODataFile( $filename ) ) {
			$drawIoFile = $filename;
		} else {
			// Probably without extension, but still contains DrawIO diagram XML?
			if ( $drawIoFileHandler->isDrawIODataContent( $fileContent ) ) {
				$drawIoFile = $filename;
			}
		}

		// Diagram data found, can be "baked" into PNG
		if ( $drawIoFile !== null ) {
			if ( $fileExtension === 'drawio' ) {
				// If DrawIO data file is like "file.drawio", let's just look for "file.drawio.png"
				$confluenceFileKey = "$spaceId---$rawPageTitle---$diagramName.png";
			} else {
				// If file does not have extension in Confluence export data,
				// ConfluenceAnalyzer adds '.unknown' extension
				// So if we need an image for 'diagram.unknown' - then should look for 'diagram.png'
				$fileNameWithoutExtension = substr( $diagramName, 0, -1 * ( strlen( $fileExtension ) + 1 ) );

				$confluenceFileKey = "$spaceId---$rawPageTitle---$fileNameWithoutExtension.png";
			}
			$imageFile = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );

			// PNG image file found, "bake" diagram data into it and replace file content
			if ( $imageFile ) {
				$imageContent = $this->dataLookup->getConfluenceFileContent( $imageFile );
				$imageContent =	$drawIoFileHandler->bakeDiagramDataIntoImage( $imageContent, $fileContent );

				$this->conversionDataWriter->replaceConfluenceFileContent( $imageFile, $imageContent );
			}
		}
	}
}
