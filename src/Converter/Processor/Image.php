<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class Image implements IProcessor {

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
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$imageNodes = $dom->getElementsByTagName( 'image' );

		foreach ( $imageNodes as $imageNode ) {
			$this->doProcessImage( $imageNode );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	private function doProcessImage( $node ): void {
		$attachmentEl = $node->getElementsByTagNae( 'attachment' );
		$urlEl = $node->getElementsByTagNae( 'url' );

		$attributes = $this->getImageAttributes( $node );

		$replacementNode = $node->ownerDocument->createTextNode( '[[Category:Broken_image]]' );
		if ( $urlEl instanceof DOMNode ) {
			$replacementNode = $this->makeImageReplacement( $node, $urlEl, $attributes );
		} elseif ( $attachmentEl instanceof DOMNode ) {
			$replacementNode = $this->makeAttachmentReplacement( $node, $attachmentEl, $attributes );
		}

		$node->parentNode->replaceChild(
			$replacementNode,
			$node
		);
	}

	/**
	 * @param DOMNode $node
	 * @return array
	 */
	private function getImageAttributes( $node ): array {
		$attributes = [];

		$width = $node->getAttribute( 'ac:width' );
		$height = $node->getAttribute( 'ac:height' );
		if ( $width !== '' || $height !== '' ) {
			$dimensions = 'px';
			if ( $height !== '' ) {
				$dimensions = 'x' . $height . $dimensions;
				$attributes['height'] = $height;
			}
			$dimensions = $width . $dimensions;
			$params[] = $dimensions;
			if ( $width !== '' ) { $attributes['width'] = $width;
			}
		}

		if ( $node->getAttribute( 'ac:class' ) !== '' ) {
			$attributes['class'][] = $node->getAttribute( 'ac:class' );
		}
		if ( $node->getAttribute( 'ac:thumbnail' ) !== '' ) {
			$params[] = 'thumb';
			$attributes['class'][] = 'thumb';
		}
		if ( !empty( $attributes['class'] ) ) {
			$attributes['class'] = implode( ' ', $attributes['class'] );
		}

		if ( $node->getAttribute( 'ac:align' ) !== '' ) {
			$params[] = $node->getAttribute( 'ac:align' );
			$attributes['align'] = $node->getAttribute( 'ac:align' );
		}

		if ( $node->getAttribute( 'ac:alt' ) !== '' ) {
			$attributes['alt'] = $node->getAttribute( 'ac:alt' );
		}

		return $attributes;
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $urlEl
	 * @param array $attributes
	 * @return DOMNode
	 */
	private function makeImageReplacement( $node, $urlEl, $attributes ): DOMNode {
		$attribs['src'] = $urlEl->getAttribute( 'ri:value' );

		$replacementNode = $node->ownerDocument->createElement( 'img' );
		foreach ( $attribs as $name => $value ) {
			$replacementNode->setAttribute( $name, $value );
		}

		return $replacementNode;
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $attachmentEl
	 * @param array $attributes
	 * @return DOMNode
	 */
	private function makeAttachmentReplacement( $node, $attachmentEl, $attributes ): DOMNode {
		$filename = $attachmentEl->getAttribute( 'ri:filename' );
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		$spaceId = $this->currentSpaceId;
		if ( $pageEl instanceof DOMElement ) {
			$rawPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
			}
		}

		$rawPageTitle = basename( $rawPageTitle );
		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";
		$targetFilename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );
		array_unshift( $params, $targetFilename );
		$replacementNode = $this->makeImageLinkWithDebugInfo( $node->ownerDocument, $params, $confluenceFileKey );

		return $replacementNode;
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @return DOMNode
	 */
	public function makeImageLink( DOMDocument $dom, array $params ): DOMNode {
		$params = array_map( 'trim', $params );

		if ( $this->nsFileRepoCompat === true ) {
			$params = $this->buildNsFileReopCompatParams( $params );
		}

		$replacementText = $this->getImageReplacement( $params );

		return $dom->createTextNode( $replacementText );
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @param string $confluenceFileKey
	 * @return DOMNode
	 */
	private function makeImageLinkWithDebugInfo( DOMDocument $dom, array $params, $confluenceFileKey ): DOMNode {
		$params = array_map( 'trim', $params );

		$debug = '';
		if ( empty( $params ) || empty( $params[0] ) ) {
			$debug = " ###BROKENIMAGE $confluenceFileKey ###";
		} elseif ( $this->nsFileRepoCompat === true ) {
			$params = $this->buildNsFileReopCompatParams( $params );
		}

		$replacementText = $this->getImageReplacement( $params );
		$replacementText .= $debug;

		return $dom->createTextNode( $replacementText );
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function buildNsFileReopCompatParams( $params ): array {
		$filename = $params[0];

		$filenameParts = explode( '_', $filename );
		if ( count( $filenameParts ) > 1 ) {
			$namespace = array_pop( $filenameParts );
			$params[0] = "$namespace:";
			$params[0] .= implode( '_', $filenameParts );
		}

		return $params;
	}

	/**
	 * @param array $params
	 * @return string
	 */
	private function getImageReplacement( $params ): string {
		return '[[File:' . implode( '|', $params ) . ']]';
	}
}