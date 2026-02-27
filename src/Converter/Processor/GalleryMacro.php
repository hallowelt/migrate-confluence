<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class GalleryMacro extends StructuredMacroProcessorBase {

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
		$bodyImages = $this->getBodyImages( $node );

		if ( !empty( $bodyImages ) ) {
			$files = $this->resolveBodyImages( $bodyImages );
		} else {
			$files = $this->getImageFiles( $params );
		}

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
	 * @param DOMNode $macro
	 * @return DOMNode[]
	 */
	private function getBodyImages( DOMNode $macro ): array {
		$images = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:image' ) {
				$images[] = $childNode;
			}
		}
		return $images;
	}

	/**
	 * @param DOMNode[] $imageNodes
	 * @return string[]
	 */
	private function resolveBodyImages( array $imageNodes ): array {
		$files = [];
		foreach ( $imageNodes as $imageNode ) {
			$attachment = null;
			$isUrl = false;

			foreach ( $imageNode->childNodes as $child ) {
				if ( $child->nodeName === 'ri:attachment' ) {
					$attachment = $child;
				} elseif ( $child->nodeName === 'ri:url' ) {
					$isUrl = true;
				}
			}

			// External URLs are not supported by MediaWiki gallery
			if ( $isUrl || $attachment === null ) {
				continue;
			}

			$filename = $attachment->getAttribute( 'ri:filename' );
			if ( $filename === '' ) {
				continue;
			}

			$spaceId = $this->currentSpaceId;
			$pageTitle = $this->rawPageTitle;

			$page = null;
			foreach ( $attachment->childNodes as $child ) {
				if ( $child->nodeName === 'ri:page' ) {
					$page = $child;
					break;
				}
			}

			if ( $page !== null ) {
				$contentTitle = $page->getAttribute( 'ri:content-title' );
				if ( $contentTitle !== '' ) {
					$pageTitle = $contentTitle;
				}
				$spaceKey = $page->getAttribute( 'ri:space-key' );
				if ( $spaceKey !== '' ) {
					$resolvedSpaceId = $this->resolveSpaceId( $spaceKey );
					if ( $resolvedSpaceId !== -1 ) {
						$spaceId = $resolvedSpaceId;
					} else {
						continue;
					}
				}
			}

			$key = $spaceId .
				'---' .
				str_replace( ' ', '_', basename( $pageTitle ) ) .
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
	 * @param string $spaceKey
	 * @return int
	 */
	private function resolveSpaceId( string $spaceKey ): int {
		$spaceId = $this->dataLookup->getSpaceIdFromSpacePrefix( $spaceKey );
		if ( $spaceId !== -1 ) {
			return $spaceId;
		}
		$prefix = $this->dataLookup->getSpacePrefixFromSpaceKey( $spaceKey );
		if ( $prefix !== -1 && $prefix !== '' ) {
			return $this->dataLookup->getSpaceIdFromSpacePrefix( $prefix );
		}
		return -1;
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
