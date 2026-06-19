<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class Image extends ConversionHelper implements IProcessor {

	/**
	 * @var FilenameResolver
	 */
	protected FilenameResolver $filenameResolver;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpaceId,
		private string $rawPageTitle,
		MigrationConfig $migrationConfig
	) {
		$this->filenameResolver = new FilenameResolver( $dataLookup, $migrationConfig );
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
		if ( $this->isImageWithPageLink( $node ) ) {
			$pageLinkReplacementNode = $this->makeImagePageLinkReplacement( $node );

			$linkBody = $node->parentNode;
			$linkNode = $linkBody->parentNode;
			$linkNode->parentNode->replaceChild(
				$pageLinkReplacementNode,
				$linkNode
			);
		} elseif ( $this->isImageWithExternalLink( $node ) ) {
			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );

			$linkNode = $node->parentNode;
			if ( $externalLinkReplacementNode === $node ) {
				// ri:url image inside external link: replace just the <ac:image>
				// with a plain text URL so the <a> survives and pandoc renders
				// [href imageUrl] instead of dropping the link entirely.
				$urlText = $this->getImageUrlText( $node );
				$node->parentNode->replaceChild(
					$this->createTextNode( $node->ownerDocument, $urlText, __METHOD__ ),
					$node
				);
			} else {
				$linkNode->parentNode->replaceChild(
					$externalLinkReplacementNode,
					$linkNode
				);
			}
		} else {
			$replacementNode = $this->createTextNode(
				$node->ownerDocument,
				$this->getCategoryBroken( 'image' ),
				__METHOD__
			);

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
	 * If this variable is false we show at least the url as link.
	 *
	 * @param DOMElement $node
	 *
	 * @return DOMElement
	 * @throws DOMException
	 */
	private function makeImageUrlReplacement( DOMElement $node ): DOMElement {
		$attributes = $this->getImageAttributes( $node->parentNode );

		$originalUrl = $node->getAttribute( 'ri:value' );
		$parsedUrl = parse_url( $originalUrl );

		if ( !isset( $parsedUrl['scheme'] ) || !isset( $parsedUrl['host'] ) || !isset( $parsedUrl['path'] ) ) {
			return $node;
		}

		// Remove url params
		$src = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];

		$replacementNode = $node->ownerDocument->createElement( 'span' );

		foreach ( $attributes as $name => $value ) {
			$replacementNode->setAttribute( $name, $value );
		}

		if ( $originalUrl !== $src ) {
			$replacementNode->setAttribute( 'data-original-url', $originalUrl );
		}

		$replacementNode->appendChild(
			$this->createTextNode( $node->ownerDocument, $src, __METHOD__ )
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
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				// TODO: Log if spaceId is null, but we should be able to
				//resolve the filename without spaceId as well, so we can continue processing
			}
		}

		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
			$this->filenameResolver->resolve( $spaceId, $rawPageTitle, $filename );

		array_unshift( $params, $targetFilename );
		$brokenFileInfo = $isBrokenFile ? '[[Category:Broken_image]]' : '';

		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";

		return $this->makeImageLinkWithDebugInfo(
			$node->ownerDocument,
			$params,
			$confluenceFileKey,
			$brokenFileInfo
		);
	}

	/**
	 * @param DOMElement $node
	 * @return DOMNode
	 */
	private function makeImagePageLinkReplacement( DOMElement $node ): DOMNode {
		$params = $this->getImageParams( $node );

		$attachmentNode = $node->getElementsByTagName( 'attachment' )->item( 0 );
		if ( !$attachmentNode || !$attachmentNode->hasAttribute( 'ri:filename' ) ) {
			return $node;
		}
		$filename = $attachmentNode->getAttribute( 'ri:filename' );
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		$linkPageTitle = $rawPageTitle;
		$spaceId = $this->currentSpaceId;
		if ( $pageEl instanceof DOMElement ) {
			if ( $pageEl->getAttribute( 'ri:content-title' ) ) {
				$linkPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			}
			$spaceKey = '';
			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			}
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				// TODO: Log if spaceId is null, but we should be able to
				// resolve the filename without spaceId as well, so we can continue processing
			}
		}

		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
				$this->filenameResolver->resolve( $spaceId, $rawPageTitle, $filename );
		array_unshift( $params, $targetFilename );

		$linkBody = $node->parentNode;
		$link = $linkBody->parentNode;

		$imagePageLinkHelper = new ImagePageLinkHelper(
			$this->dataLookup,
			$this->currentSpaceId,
			$linkPageTitle
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

		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";

		$replacementNode = $this->makeImageLinkWithDebugInfo(
			$node->ownerDocument,
			$params,
			$confluenceFileKey,
			$brokenPageLinkInfo
		);

		return $replacementNode;
	}

	/**
	 * @param DOMElement $node
	 * @return DOMNode
	 */
	private function makeImageExternalLinkReplacement( DOMElement $node ): DOMNode {
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
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				// TODO: Log if spaceId is null, but we should be able to
				// resolve the filename without spaceId as well, so we can continue processing
			}
		}

		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
				$this->filenameResolver->resolve( $spaceId, $rawPageTitle, $filename );
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

		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";

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

		return $this->createTextNode( $dom, $replacementText, __METHOD__ );
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
	 * @param DOMElement $node
	 *
	 * @return bool
	 */
	private function isImageWithPageLink( DOMElement $node ): bool {
		if ( $node->parentNode->nodeName === 'ac:link-body' ) {
			return true;
		}

		return false;
	}

	/**
	 * Extracts the plain URL string from an <ac:image> node's <ri:url> child,
	 * stripping query parameters. Returns an empty string if not applicable.
	 *
	 * @param DOMElement $imageNode
	 * @return string
	 */
	private function getImageUrlText( DOMElement $imageNode ): string {
		foreach ( $imageNode->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ri:url' ) {
				$parsedUrl = parse_url( $child->getAttribute( 'ri:value' ) );
				if ( isset( $parsedUrl['scheme'] ) && isset( $parsedUrl['host'] ) && isset( $parsedUrl['path'] ) ) {
					return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
				}
			}
		}
		return '';
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return bool
	 */
	private function isImageWithExternalLink( DOMElement $node ): bool {
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
