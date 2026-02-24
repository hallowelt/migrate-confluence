<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class GalleryMacro extends StructuredMacroProcessorBase {

	private const IMAGE_EXTENSIONS = [
		'jpg',
		'jpeg',
		'png',
		'gif',
		'svg',
		'bmp',
		'webp',
		'tiff',
		'tif',
	];

	/** @var ConversionDataLookup */
	private $dataLookup;

	/** @var int */
	private $currentSpaceId;

	/** @var string */
	private $rawPageTitle;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct(
		ConversionDataLookup $dataLookup,
		int $currentSpaceId,
		string $rawPageTitle
	) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'gallery';
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->getMacroParams( $node );
		$files = $this->getImageFiles( $params );

		if ( empty( $files ) ) {
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( $this->getBrokenMacroCategroy() ),
				$node
			);

			return;
		}

		$galleryTag = '<gallery';
		if ( isset( $params['title'] ) && $params['title'] !== '' ) {
			$galleryTag .= ' caption="' . htmlspecialchars( $params['title'] ) . '"';
		}
		if ( isset( $params['columns'] ) && $params['columns'] !== '' ) {
			$galleryTag .= ' perrow="' . (int)$params['columns'] . '"';
		}
		$galleryTag .= ">\n";
		foreach ( $files as $file ) {
			$galleryTag .= $file . "\n";
		}
		$galleryTag .= '</gallery>';

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $galleryTag ),
			$node
		);
	}

	/**
	 * @param array $params
	 *
	 * @return string[]
	 */
	private function getImageFiles( array $params ): array {
		if ( isset( $params['include'] ) && $params['include'] !== '' ) {
			return $this->resolveIncludedFiles( $params['include'] );
		}

		$allFiles = $this->dataLookup->getTargetFileTitlesForPage(
			$this->currentSpaceId,
			$this->rawPageTitle
		);

		$exclude = [];
		if ( isset( $params['exclude'] ) && $params['exclude'] !== '' ) {
			$exclude = array_map( 'trim', explode( ',', $params['exclude'] ) );
		}

		$files = [];
		foreach ( $allFiles as $file ) {
			if ( !$this->isImageFile( $file ) ) {
				continue;
			}
			if ( in_array( $file, $exclude ) ) {
				continue;
			}
			$files[] = $file;
		}

		return $files;
	}

	/**
	 * @param string $include Comma-separated filenames
	 *
	 * @return string[]
	 */
	private function resolveIncludedFiles( string $include ): array {
		$filenames = array_map( 'trim', explode( ',', $include ) );
		$files = [];
		foreach ( $filenames as $filename ) {
			$key = $this->currentSpaceId .
				'---' .
				str_replace( ' ', '_', basename( $this->rawPageTitle ) ) .
				'---' .
				str_replace( ' ', '_', $filename );
			$targetTitle = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $key );
			if ( $targetTitle !== '' ) {
				$files[] = $targetTitle;
			}
		}

		return $files;
	}

	/**
	 * @param string $filename
	 *
	 * @return bool
	 */
	private function isImageFile( string $filename ): bool {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		return in_array( $ext, self::IMAGE_EXTENSIONS );
	}

	/**
	 * @param DOMNode $macro
	 *
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
