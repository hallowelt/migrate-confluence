<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class Image extends ConversionHelper implements IProcessor {

	protected FilenameResolver $filenameResolver;

	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpaceId,
		private string $rawPageTitle,
		MigrationConfig $migrationConfig
	) {
		$this->filenameResolver = new FilenameResolver( $dataLookup, $migrationConfig );
	}

	public function process( DOMDocument $dom ): void {
		$nonLiveList = [];

		foreach ( $dom->getElementsByTagName( 'image' ) as $imageNode ) {
			$nonLiveList[] = $imageNode;
		}

		foreach ( $dom->getElementsByTagName( 'img' ) as $imageNode ) {
			$nonLiveList[] = $imageNode;
		}

		foreach ( $nonLiveList as $imageNode ) {
			$this->doProcessImage( $imageNode );
		}
	}

	private function doProcessImage( DOMElement $node ): void {
		if ( $node->nodeName === 'img' ) {
			$this->doProcessRawImg( $node );

			return;
		}

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
			$externalLinkReplacementNode = $this->makeImageExternalLinkReplacement( $node );

			$linkNode = $node->parentNode;
			if ( $externalLinkReplacementNode === $node ) {
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

	/**
	 * Handles a raw HTML <img> node (as opposed to an <ac:image>). Its source
	 * is in the `src` attribute rather than a child element. External images
	 * are replaced by their cleaned URL as plain text; anything else (e.g. a
	 * relative/internal src) is left untouched.
	 */
	private function doProcessRawImg( DOMElement $node ): void {
		$urlText = $this->getExternalUrlText( $node->getAttribute( 'src' ) );
		if ( $urlText === '' ) {
			// relative/internal src (no scheme/host) -> not handled here, leave as-is
			return;
		}

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, $urlText, __METHOD__ ),
			$node
		);
	}

	/**
	 * Handle ri:url image inside external link or link in <img>
	 * Replace with a plain text URL so the <a> survives and pandoc renders
	 * [href imageUrl] instead of dropping the link entirely.
	 *
	 * Cleaned external URL (scheme://host/path, query stripped), or '' if not external.
	 */
	private function getExternalUrlText( string $url ): string {
		$parsed = parse_url( $url );
		if ( !isset( $parsed['scheme'] ) || !isset( $parsed['host'] ) ) {
			return '';
		}
		return $parsed['scheme'] . '://' . $parsed['host'] . ( $parsed['path'] ?? '' );
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

	private function getImageReplacement( array $params ): string {
		return '[[File:' . implode( '|', $params ) . ']]';
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
