<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Handles <ac:image> nodes. Raw HTML <img> nodes are handled by RawImage.
 */
class Image extends ImageProcessorBase {

	/**
	 * Inline elements that may sit between an <a> and the <ac:image> it encloses.
	 */
	private const INLINE_WRAPPERS = [ 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'code' ];

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
			$anchor = $this->getEnclosingAnchor( $node );
			$href = $anchor->getAttribute( 'href' );
			$urlText = $this->getImageUrlText( $node );

			if ( $urlText !== '' ) {
				if ( !$this->hasDimensions( $node ) ) {
					$this->replaceAnchorWithTextLink( $anchor, $href, $urlText );

					return;
				}

				$anchor->parentNode->replaceChild(
					$this->makePlainUrlImageReplacement( $node, $href ),
					$anchor
				);

				return;
			}

			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );
			if ( $externalLinkReplacementNode !== $node ) {
				$anchor->parentNode->replaceChild(
					$externalLinkReplacementNode,
					$anchor
				);

				return;
			}

			$this->replaceWithBrokenExternalLink( $anchor, $href );

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
				$replacementNode = $this->makePlainUrlImageReplacement( $node );
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

	private function hasDimensions( DOMElement $imageNode ): bool {
		return $imageNode->getAttribute( 'ac:width' ) !== ''
			|| $imageNode->getAttribute( 'ac:height' ) !== '';
	}

	/**
	 * MediaWiki does not render an img tag pointing to an external url.
	 * Images with dimensions are wrapped in the {{PlainUrlImage}} template
	 * so the wiki side can decide how to render them. Without dimensions the
	 * url (stripped of query params) is output as plain text.
	 */
	private function makePlainUrlImageReplacement( DOMElement $imageNode, string $link = '' ): DOMNode {
		$urlText = $this->getImageUrlText( $imageNode );
		if ( $urlText === '' ) {
			return $imageNode;
		}

		if ( !$this->hasDimensions( $imageNode ) ) {
			return $this->createTextNode( $imageNode->ownerDocument, $urlText, __METHOD__ );
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

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			"PlainUrlImage"
		);

		$replacementText = '{{PlainUrlImage|' . implode( '|', $params ) . '}}';

		return $this->createTextNode( $imageNode->ownerDocument, $replacementText, __METHOD__ );
	}

	/**
	 * Replaces the anchor (including any inline wrappers around the image)
	 * with a fresh <a href="$href">$text</a>.
	 */
	private function replaceAnchorWithTextLink( DOMElement $anchor, string $href, string $text ): DOMElement {
		$dom = $anchor->ownerDocument;

		$newAnchor = $dom->createElement( 'a' );
		$newAnchor->setAttribute( 'href', $href );
		$newAnchor->appendChild( $this->createTextNode( $dom, $text, __METHOD__ ) );

		$anchor->parentNode->replaceChild( $newAnchor, $anchor );

		return $newAnchor;
	}

	/**
	 * The image inside the link could not be resolved (no ri:url, no usable
	 * ri:attachment). Keep the link itself, using its href as link text,
	 * and mark the page as containing a broken image.
	 */
	private function replaceWithBrokenExternalLink( DOMElement $anchor, string $href ): void {
		$newAnchor = $this->replaceAnchorWithTextLink( $anchor, $href, $href );

		$newAnchor->parentNode->insertBefore(
			$this->createTextNode(
				$newAnchor->ownerDocument,
				$this->getCategoryBroken( 'image' ),
				__METHOD__
			),
			$newAnchor->nextSibling
		);
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

		$link = $this->getEnclosingAnchor( $node );
		if ( $link === null ) {
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

	/**
	 * Returns the <a> enclosing the image, looking through inline wrappers
	 * like <span> or <strong>. Returns null if there is none.
	 */
	private function getEnclosingAnchor( DOMElement $node ): ?DOMElement {
		$current = $node->parentNode;
		while ( $current instanceof DOMElement ) {
			if ( $current->nodeName === 'a' ) {
				return $current;
			}
			if ( !in_array( $current->nodeName, self::INLINE_WRAPPERS, true ) ) {
				return null;
			}
			$current = $current->parentNode;
		}
		return null;
	}

	private function isImageWithExternalLink( DOMElement $node ): bool {
		$anchor = $this->getEnclosingAnchor( $node );
		if ( $anchor === null || !$anchor->hasAttribute( 'href' ) ) {
			return false;
		}

		$parsedUrl = parse_url( $anchor->getAttribute( 'href' ) );

		return isset( $parsedUrl['scheme'] );
	}

}
