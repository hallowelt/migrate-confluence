<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class Image implements IProcessor {

	/**
	 * @var ConversionDataLookup
	 */
	protected ConversionDataLookup $dataLookup;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	/**
	 * @var string
	 */
	protected string $rawPageTitle;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$imageNodes = $dom->getElementsByTagName( 'image' );

		$nonLiveList = [];
		foreach ( $imageNodes as $imageNode ) {
			$nonLiveList[] = $imageNode;
		}

		foreach ( $nonLiveList as $imageNode ) {
			$this->doProcessImage( $imageNode );
		}
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return void
	 * @throws DOMException
	 */
	private function doProcessImage( DOMElement $node ): void {
		$replacementNode = $node->ownerDocument->createTextNode( '[[Category:Broken_image]]' );

		if ( $node instanceof DOMElement ) {
			foreach ( $node->childNodes as $childNode ) {
				if ( $childNode instanceof DOMElement === false ) {
					continue;
				}
				if ( $childNode->nodeName === 'ri:url' ) {
					$replacementNode = $this->makeImageUrlReplacement( $childNode );
				} elseif ( $childNode->nodeName === 'ri:attachment' ) {
					$replacementNode = $this->makeImageAttachmentReplacement( $childNode );
				}
			}
		}

		$isImageWithPageLink = $this->isImageWithPageLink( $node );
		$isImageWithExternalLink = $this->isImageWithExternalLink( $node );
		if ( $isImageWithPageLink ) {
			$pageLinkReplacementNode = $this->makeImagePageLinkReplacement( $node );

			$linkBody = $node->parentNode;
			$linkNode = $linkBody->parentNode;
			$linkNode->parentNode->replaceChild(
				$pageLinkReplacementNode,
				$linkNode
			);
		} elseif ( $isImageWithExternalLink ) {
			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );

			$linkNode = $node->parentNode;
			$linkNode->parentNode->replaceChild(
				$externalLinkReplacementNode,
				$linkNode
			);
		} else {
			$node->parentNode->replaceChild(
				$replacementNode,
				$node
			);
		}
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return array
	 */
	private function getImageAttributes( DOMElement $node ): array {
		$attributes = [];
		$width = '';
		$height = '';

		if ( $node->hasAttribute( 'ac:width' ) ) {
			$width = $node->getAttribute( 'ac:width' );
		}
		if ( $node->hasAttribute( 'ac:height' ) ) {
			$height = $node->getAttribute( 'ac:height' );
		}
		if ( $width !== '' || $height !== '' ) {
			if ( $height !== '' ) {
				$attributes['height'] = $height;
			}
			if ( $width !== '' ) {
				$attributes['width'] = $width;
			}
		}

		$classes = [];
		if ( $node->getAttribute( 'ac:class' ) !== '' ) {
			$classes[] = $node->getAttribute( 'ac:class' );
		}
		if ( $node->getAttribute( 'ac:thumbnail' ) !== '' ) {
			$classes[] = 'thumb';
		}
		if ( !empty( $classes ) ) {
			$attributes['class'] = implode( ' ', $classes );
		}

		if ( $node->getAttribute( 'ac:align' ) !== '' ) {
			$attributes['align'] = $node->getAttribute( 'ac:align' );
		}

		if ( $node->getAttribute( 'ac:alt' ) !== '' ) {
			$attributes['alt'] = $node->getAttribute( 'ac:alt' );
		}

		return $attributes;
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return array
	 */
	private function getImageParams( DOMElement $node ): array {
		$params = [];

		$width = $node->getAttribute( 'ac:width' );
		$height = $node->getAttribute( 'ac:height' );
		if ( $width !== '' || $height !== '' ) {
			$dimensions = 'px';
			if ( $height !== '' ) {
				$dimensions = 'x' . $height . $dimensions;
			}
			$dimensions = $width . $dimensions;
			$params[] = $dimensions;
		}

		if ( $node->getAttribute( 'ac:thumbnail' ) !== '' ) {
			$params[] = 'thumb';
		}

		if ( $node->getAttribute( 'ac:align' ) !== '' ) {
			$params[] = $node->getAttribute( 'ac:align' );
		}

		return $params;
	}

	/**
	 * MediaWiki does not render an img tag.
	 * But with $wgAllowExternalImages it can show external images.
	 * If this varaiable is false we show at least the url as link.
	 *
	 * @param DOMElement $node
	 *
	 * @return DOMNode
	 * @throws DOMException
	 */
	private function makeImageUrlReplacement( DOMElement $node ): DOMNode {
		$attributes = $this->getImageAttributes( $node->parentNode );
		$src = $node->getAttribute( 'ri:value' );

		$replacementNode = $node->ownerDocument->createElement( 'span' );

		foreach ( $attributes as $name => $value ) {
			$replacementNode->setAttribute( $name, $value );
		}

		$replacementNode->appendChild(
			$node->ownerDocument->createTextNode( $src )
		);

		return $replacementNode;
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return DOMNode
	 */
	private function makeImageAttachmentReplacement( DOMElement $node ): DOMNode {
		$params = $this->getImageParams( $node->parentNode );

		if ( !$node->hasAttribute( 'ri:filename' ) ) {
			return $node;
		}
		$filename = $node->getAttribute( 'ri:filename' );
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		$spaceId = $this->currentSpaceId;
		if ( $pageEl instanceof DOMElement ) {
			if ( $pageEl->getAttribute( 'ri:content-title' ) ) {
				$rawPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			}
			$spaceKey = '';
			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			}
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
			}
		}

		$rawPageTitle = basename( $rawPageTitle );

		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
			$this->dataLookup->resolveFileTitle( $confluenceFileKey, $filename );
		array_unshift( $params, $targetFilename );
		$brokenFileInfo = $isBrokenFile ? '[[Category:Broken_image]]' : '';
		$replacementNode = $this->makeImageLinkWithDebugInfo( $node->ownerDocument, $params, $confluenceFileKey, $brokenFileInfo );

		return $replacementNode;
	}

	/**
	 * @param DomElement $node
	 * @return DOMNode
	 */
	private function makeImagePageLinkReplacement( DomElement $node ): DOMNode {
		$params = $this->getImageParams( $node );

		$attachmentNode = $node->getElementsByTagName( 'attachment' )->item( 0 );
		if ( !$attachmentNode || !$attachmentNode->hasAttribute( 'ri:filename' ) ) {
			return $node;
		}
		$filename = $attachmentNode->getAttribute( 'ri:filename' );
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		$spaceId = $this->currentSpaceId;
		if ( $pageEl instanceof DOMElement ) {
			if ( $pageEl->getAttribute( 'ri:content-title' ) ) {
				$rawPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			}
			$spaceKey = '';
			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			}
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
			}
		}

		$rawPageTitle = basename( $rawPageTitle );
		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
			$this->dataLookup->resolveFileTitle( $confluenceFileKey, $filename );
		array_unshift( $params, $targetFilename );

		$linkBody = $node->parentNode;
		$link = $linkBody->parentNode;

		$imagePageLinkHelper = new ImagePageLinkHelper(
			$this->dataLookup,
			$this->currentSpaceId,
			$rawPageTitle
		);
		$target = $imagePageLinkHelper->getLinkTarget( $link );
		if ( !empty( $target ) ) {
			$params[] = "link=$target";
		}

		$isBrokenPageLink = $imagePageLinkHelper->isBrokenLink();
		$brokenPageLinkInfo = '';
		if ( $isBrokenPageLink ) {
			$brokenPageLinkInfo = '[[Category:Broken_image_page_link]]';
		}
		if ( $isBrokenFile ) {
			$brokenPageLinkInfo .= '[[Category:Broken_image]]';
		}

		$replacementNode = $this->makeImageLinkWithDebugInfo(
			$node->ownerDocument,
			$params,
			$confluenceFileKey,
			$brokenPageLinkInfo
		);

		return $replacementNode;
	}

	/**
	 * @param DomElement $node
	 * @return DOMNode
	 */
	private function makeImageExternalLinkReplacement( DomElement $node ): DOMNode {
		$params = $this->getImageParams( $node );

		$attachmentNode = $node->getElementsByTagName( 'attachment' )->item( 0 );
		if ( !$attachmentNode || !$attachmentNode->hasAttribute( 'ri:filename' ) ) {
			return $node;
		}
		$filename = $attachmentNode->getAttribute( 'ri:filename' );
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		$spaceId = $this->currentSpaceId;
		if ( $pageEl instanceof DOMElement ) {
			if ( $pageEl->getAttribute( 'ri:content-title' ) ) {
				$rawPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			}
			$spaceKey = '';
			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			}
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
			}
		}

		$rawPageTitle = basename( $rawPageTitle );
		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
			$this->dataLookup->resolveFileTitle( $confluenceFileKey, $filename );
		array_unshift( $params, $targetFilename );

		$brokenLinkInfo = '';
		$target = '';

		$link = $node->parentNode;
		if ( $link instanceof DOMElement === false ) {
			$brokenLinkInfo = '[[Category:Broken_image_external_link]]';
		} else {
			$target = $link->getAttribute( 'href' );
		}

		if ( $isBrokenFile ) {
			$brokenLinkInfo .= '[[Category:Broken_image]]';
		}

		if ( !empty( $target ) ) {
			$params[] = "link=$target";
		}

		$replacementNode = $this->makeImageLinkWithDebugInfo(
			$node->ownerDocument,
			$params,
			$confluenceFileKey,
			$brokenLinkInfo
		);

		return $replacementNode;
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @return DOMNode
	 */
	public function makeImageLink( DOMDocument $dom, array $params ): DOMNode {
		$params = array_map( 'trim', $params );

		$replacementText = $this->getImageReplacement( $params );

		return $dom->createTextNode( $replacementText );
	}

	/**
	 * @param DOMDocument $dom
	 * @param array $params
	 * @param string $confluenceFileKey
	 * @param string $debug
	 *
	 * @return DOMNode
	 */
	private function makeImageLinkWithDebugInfo( DOMDocument $dom, array $params,
		string $confluenceFileKey, string $debug = '' ): DOMNode {
		$params = array_map( 'trim', $params );

		if ( empty( $params ) || empty( $params[0] ) ) {
			$debug .= " ###BROKENIMAGE $confluenceFileKey ###";
		}

		$replacementText = $this->getImageReplacement( $params );
		$replacementText .= $debug;

		return $dom->createTextNode( $replacementText );
	}

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	private function getImageReplacement( array $params ): string {
		return '[[File:' . implode( '|', $params ) . ']]';
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return bool
	 */
	private function isImageWithPageLink( DOMNode $node ): bool {
		if ( $node->parentNode->nodeName === 'ac:link-body' ) {
			return true;
		}

		return false;
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return bool
	 */
	private function isImageWithExternalLink( DOMNode $node ): bool {
		if ( $node->parentNode->nodeName !== 'a' ) {
			return false;
		}

		$anchor = $node->parentNode;
		if ( $anchor instanceof DOMElement === false ) {
			return false;
		}

		if ( !$anchor->hasAttribute( 'href' ) ) {
			return false;
		}

		$href = $anchor->getAttribute( 'href' );
		$parsedUrl = parse_url( $href );

		if ( isset( $parsedUrl['scheme'] ) ) {
			return true;
		}

		return false;
	}

}
