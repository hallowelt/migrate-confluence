<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Handles <ac:image> nodes. Raw HTML <img> nodes are handled by RawImage.
 */
class Image extends ImageProcessorBase {

	public function process( DOMDocument $dom ): void {
		$imageNodes = [];

		foreach ( $dom->getElementsByTagName( 'image' ) as $imageNode ) {
			$imageNodes[] = $imageNode;
		}

		foreach ( $imageNodes as $imageNode ) {
			$this->doProcessImage( $imageNode );
		}
	}

	private function doProcessImage( DOMElement $node ): void {
		if ( $this->isImageWithPageLink( $node ) ) {
			$pageLinkReplacementNode = $this->makeImagePageLinkReplacement( $node );

			$linkBody = $node->parentNode;
			$linkNode = $linkBody->parentNode;
			$linkNode->parentNode->replaceChild(
				$pageLinkReplacementNode,
				$linkNode
			);

			return;
		}

		if ( $this->isImageWithExternalLink( $node ) ) {
			$urlText = $this->getImageUrlText( $node );
			if ( $urlText !== '' ) {
				$anchor = $node->parentNode;
				$link = $anchor instanceof DOMElement ? $anchor->getAttribute( 'href' ) : '';
				$anchor->parentNode->replaceChild(
					$this->makePlainUrlImageReplacement( $node, $link ),
					$anchor
				);

				return;
			}

			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );

			$linkNode = $node->parentNode;
			if ( $externalLinkReplacementNode === $node ) {
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

			return;
		}

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
	 * MediaWiki does not render an img tag pointing to an external url.
	 * Wrap the url (and, if present, the link it is enclosed by) in the
	 * {{PlainUrlImage}} template so the wiki side can decide how to render it.
	 */
	private function makePlainUrlImageReplacement( DOMElement $imageNode, string $link = '' ): DOMNode {
		$urlText = $this->getImageUrlText( $imageNode );
		if ( $urlText === '' ) {
			return $imageNode;
		}

		$params = [];
		if ( $link !== '' ) {
			$params[] = "link=$link";
		}
		$params[] = "url=$urlText";

		$height = $imageNode->getAttribute( 'ac:height' );
		if ( $height !== '' ) {
			$params[] = "height=$height";
		}

		$width = $imageNode->getAttribute( 'ac:width' );
		if ( $width !== '' ) {
			$params[] = "width=$width";
		}

		$replacementText = '{{PlainUrlImage|' . implode( '|', $params ) . '}}';

		return $this->createTextNode( $imageNode->ownerDocument, $replacementText, __METHOD__ );
	}

	/**
	 * MediaWiki does not render an img tag.
	 * But with $wgAllowExternalImages it can show external images.
	 * If this variable is false we show at least the url as link.
	 */
	private function makeImageUrlReplacement( DOMElement $node ): DOMNode {
		$urlText = $this->getExternalUrlText( $node->getAttribute( 'ri:value' ) );
		if ( $urlText === '' ) {
			return $node;
		}

		return $this->createTextNode( $node->ownerDocument, $urlText, __METHOD__ );
	}

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

			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );

				if ( !empty( $spaceKey ) ) {
					$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				}
			}
		}

		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
			$this->filenameResolver->resolve( $spaceId, $rawPageTitle, $filename );

		array_unshift( $params, $targetFilename );
		$brokenFileInfo = $isBrokenFile ? $this->getCategoryBroken( 'image' ) : '';

		$confluenceFileKey = "$spaceId---$rawPageTitle---$filename";

		return $this->makeImageLinkWithDebugInfo(
			$node->ownerDocument,
			$params,
			$confluenceFileKey,
			$brokenFileInfo
		);
	}

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

			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );

				if ( !empty( $spaceKey ) ) {
					$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				}
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
			$brokenPageLinkInfo = $this->getCategoryBroken( 'image_page_link' );
		}
		if ( $isBrokenFile ) {
			$brokenPageLinkInfo .= $this->getCategoryBroken( 'image' );
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

			if ( $pageEl->getAttribute( 'ri:space-key' ) ) {
				$spaceKey = $pageEl->getAttribute( 'ri:space-key' );

				if ( !empty( $spaceKey ) ) {
					$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
				}
			}
		}

		[ 'title' => $targetFilename, 'isBroken' => $isBrokenFile ] =
				$this->filenameResolver->resolve( $spaceId, $rawPageTitle, $filename );
		array_unshift( $params, $targetFilename );

		$brokenLinkInfo = '';
		$target = '';

		$link = $node->parentNode;
		if ( $link instanceof DOMElement === false ) {
			$brokenLinkInfo = $this->getCategoryBroken( 'image_external_link' );
		} else {
			$target = $link->getAttribute( 'href' );
		}

		if ( $isBrokenFile ) {
			$brokenLinkInfo .= $this->getCategoryBroken( 'image' );
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

	private function isImageWithPageLink( DOMElement $node ): bool {
		if ( $node->parentNode->nodeName === 'ac:link-body' ) {
			return true;
		}

		return false;
	}

	/**
	 * Extracts the plain URL string from an <ac:image> node's <ri:url> child,
	 * stripping query parameters. Returns an empty string if not applicable.
	 */
	private function getImageUrlText( DOMElement $imageNode ): string {
		foreach ( $imageNode->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ri:url' ) {
				return $this->getExternalUrlText( $child->getAttribute( 'ri:value' ) );
			}
		}
		return '';
	}

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
