<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReader;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;

class DrawioMacro extends StructuredMacroProcessorBase {

	/**
	 * @param IConverterDataReader $reader
	 * @param ConversionDataWriter $conversionDataWriter
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct(
		protected IConverterDataReader $reader,
		protected ConversionDataWriter $conversionDataWriter,
		protected int $currentSpaceId,
		protected string $rawPageTitle ) {
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'drawio';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );

		if ( isset( $params['diagramName'] ) ) {
			$paramsString = $this->makeParamsString( $params );

			$node->parentNode->replaceChild(
				$this->createTextNode( $node->ownerDocument, "{{Drawio$paramsString}}", __METHOD__ ),
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

	/**
	 * @param string $diagramName
	 * @return string
	 */
	private function getFilename( string $diagramName ): string {
		$spaceId = $this->currentSpaceId;
		$filename = $this->reader->getWikiFileTitleFromSpaceId(
			$spaceId,
			$this->rawPageTitle,
			$diagramName
		) ?? '';
		$originalFilename = $filename;

		if ( $this->getFileExtension( $filename ) === 'unknown' ) {
			$filename = substr( $filename, 0, strlen( $filename ) - strlen( '.unknown' ) );
		}

		// Let's look for a corresponding PNG image
		// If such image exists - then file we are currently processing is most likely DrawIO data file

		// If DrawIO data file is like "file.drawio", let's just look for "file.drawio.png"
		// It works with any extension, not only ".drawio"
		// For example, diagram name could be "something.OG" - then diagram image will be "something.OG.png"

		if ( strtolower( $this->getFileExtension( $filename ) ) !== 'png' ) {
			// find png — the image file has the same name as the data file plus ".png"
			$drawioDataFilename = $originalFilename;
			$drawioImageFilename = $this->reader->getWikiFileTitleFromSpaceId(
				$spaceId,
				$this->rawPageTitle,
				$diagramName . '.png'
			) ?? '';
		} else {
			// find data
			$drawioImageFilename = $filename;
			$diagramName = substr( $filename, 0, strlen( $filename ) - strlen( '.png' ) );
			$drawioDataFilename = $this->reader->getWikiFileTitleFromSpaceId(
				$spaceId,
				$this->rawPageTitle,
				$diagramName
			) ?? '';
			// Maybe png = PNG
			if ( $drawioDataFilename === '' ) {
				$diagramName = substr( $filename, 0, strlen( $filename ) - strlen( '.PNG' ) );
				$drawioDataFilename = $this->reader->getWikiFileTitleFromSpaceId(
					$spaceId,
					$this->rawPageTitle,
					$diagramName
				) ?? '';
			}
		}

		if ( ( $drawioDataFilename !== '' ) && ( $drawioImageFilename !== '' ) ) {
			// Bring image and data together
			$this->bakeDrawIODataInPNG( $drawioDataFilename, $drawioImageFilename );
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
	 * @param string $drawioDataFilename
	 * @param string $drawioImageFilename
	 * @return void
	 */
	private function bakeDrawIODataInPNG( string $drawioDataFilename, string $drawioImageFilename ): void {
		// Diagram file could be not an '.png' image, but just a text file with diagram XML
		// In that case it may have '.drawio' extension, or may not have extension at all
		// Anyway, in case with DrawIO diagram there should be a corresponding '.png' image:
		// *	If there is a diagram file 'diagram' - there should be corresponding 'diagram.png' image
		// *	If there is a diagram file 'diagram.drawio' - there will be 'diagram.drawio.png' image

		// Our goal here is to encode and "bake" diagram XML into corresponding '.png' image
		// Because that's how BlueSpice will process it, it needs just single PNG image with "baked" diagram XML

		$drawIoFileHandler = new DrawIOFileHandler();

		// Need to make sure that file is really DrawIO file with diagram data
		$dataFileContent = $this->reader->getAttachmentContent( $drawioDataFilename );

		// PNG image file found, "bake" diagram data into it and replace file content
		$imageFileContent = $this->reader->getAttachmentContent( $drawioImageFilename );
		if ( $dataFileContent === null || $imageFileContent === null ) {
			echo ( "Drawio error $this->rawPageTitle: $drawioImageFilename, $drawioDataFilename" );
			return;
		}
		$imageFileContent =	$drawIoFileHandler->bakeDiagramDataIntoImage( $imageFileContent, $dataFileContent );

		$this->conversionDataWriter->replaceConfluenceFileContent( $drawioImageFilename, $imageFileContent );
	}
}
