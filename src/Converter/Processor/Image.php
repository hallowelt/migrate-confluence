<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * Handles <ac:image> nodes. Raw HTML <img> nodes are handled by RawImage.
 */
class Image extends ImageProcessorBase {

	public function __construct(
		protected IConverterDataWriter $writer,
		protected DBConversionDataLookup $dataLookup,
		protected int $currentSpaceId,
		protected string $rawPageTitle,
		MigrationConfig $migrationConfig
	) {
		parent::__construct( $writer, $this->currentSpaceId );
		$this->filenameResolver = new FilenameResolver( $dataLookup, $migrationConfig );
	}

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

		$enclosingExternalLink = $this->getEnclosingExternalLink( $node );

		if ( $enclosingExternalLink ) {
			$href = $enclosingExternalLink->getAttribute( 'href' );
			$url = $this->getImageUrl( $node );

			if ( $url !== '' ) {
				$enclosingExternalLink->parentNode->replaceChild(
					$this->makeExternalImageReplacement( $node, $href ),
					$enclosingExternalLink
				);

				return;
			}

			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );
			if ( $externalLinkReplacementNode !== $node ) {
				$enclosingExternalLink->parentNode->replaceChild(
					$externalLinkReplacementNode,
					$enclosingExternalLink
				);

				return;
			}

			$this->replaceWithBrokenExternalLink( $enclosingExternalLink, $href );

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
				$replacementNode = $this->makeExternalImageReplacement( $node );
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
	 * Images with dimensions are wrapped in the {{ExternalImage}} template
	 * so the wiki side can decide how to render them. Without dimensions the
	 * url (stripped of query params) is output as plain text.
	 */
	private function makeExternalImageReplacement( DOMElement $imageNode, string $link = '' ): DOMNode {
		$urlText = $this->getImageUrl( $imageNode );
		if ( $urlText === '' ) {
			return $imageNode;
		}

		$replacementText = $this->buildExternalImageTemplate(
			$this->getExternalImageParams( $imageNode ),
			$urlText,
			$link
		);

		return $this->createTextNode( $imageNode->ownerDocument, $replacementText, __METHOD__ );
	}

	/**
	 * Collects the <ac:image> node's attributes (e.g. ac:height, ac:width,
	 * ac:align, ...) as "name=value" template params, stripping the "ac:"
	 * namespace prefix. height/width come first for a stable, predictable
	 * param order.
	 */
	private function getExternalImageParams( DOMElement $imageNode ): array {
		$params = [];

		foreach ( [ 'height', 'width' ] as $name ) {
			$value = $imageNode->getAttribute( "ac:$name" );
			if ( $value !== '' ) {
				$params[$name] = $value;
			}
		}

		foreach ( $imageNode->attributes as $attribute ) {
			$name = $attribute->nodeName;
			if ( strpos( $name, 'ac:' ) !== 0 ) {
				continue;
			}
			$name = substr( $name, strlen( 'ac:' ) );

			if ( isset( $params[$name] ) ) {
				continue;
			}

			$params[$name] = $attribute->value;
		}

		return $params;
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

		$link = $this->findEnclosingAnchor( $node );
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
	private function getImageUrl( DOMElement $imageNode ): string {
		foreach ( $imageNode->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ri:url' ) {
				return $this->modifyExternalImageUrl( $child->getAttribute( 'ri:value' ) );
			}
		}
		return '';
	}

	private function getImageReplacement( array $params ): string {
		return '[[File:' . implode( '|', $params ) . ']]';
	}

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
}
