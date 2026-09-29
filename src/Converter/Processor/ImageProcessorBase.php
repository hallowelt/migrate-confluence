<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;

/**
 * Shared logic used by both the <ac:image> processor (Image) and the raw
 * HTML <img> processor (ImgHtml).
 */
abstract class ImageProcessorBase extends ConversionHelper implements IProcessor {

	private const IMAGE_EXTENSION_FALLBACK = 'jpg';

	/**
	 * Inline elements that may sit between an <a> and the image it encloses.
	 */
	protected const INLINE_WRAPPERS = [ 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'code' ];

	protected FilenameResolver $filenameResolver;

	public function __construct(
		protected IConverterDataWriter $writer,
		protected int $currentSpaceId,
	) {
	}

	/**
	 *	Ensures compatibility with mediawikis handling of external image urls.
	 *
	 *	Handle ri:url image inside external link or link in <img>
	 *	Replace with a plain text URL so the <a> survives and pandoc renders
	 *	[href imageUrl] instead of dropping the link entirely.
	 *
	 *	HACK: If the URL has query params, they are kept and a file extension is appended
	 *	as a fragment at the end of the string, so MediaWiki still recognizes the file type.
	 *	Default extension if none is present: .jpg
	 *	e.g.
	 *	https://example.com/download/attachments/1933361/image.png?version=1&modificationDate=1724938529723
	 *	->
	 *	https://example.com/download/attachments/1933361/image.png?version=1&modificationDate=1724938529723#.png
	 *
	 *	Cleaned external image URL (scheme://host/path[?query#.ext]), or '' if not external.
	 */
	protected function modifyExternalImageUrl( string $url ): string {
		$parsed = parse_url( $url );
		if ( !isset( $parsed['scheme'] ) || !isset( $parsed['host'] ) ) {
			return '';
		}

		$path = $parsed['path'] ?? '';
		$result = $parsed['scheme'] . '://' . $parsed['host'] . $path;

		if ( isset( $parsed['query'] ) && $parsed['query'] !== '' ) {
			$extension = pathinfo( $path, PATHINFO_EXTENSION );
			if ( $extension === '' ) {
				$extension = self::IMAGE_EXTENSION_FALLBACK;
			}
			$result .= '?' . $parsed['query'] . '#.' . $extension;
		}

		return $result;
	}

	/**
	 * Returns the <a> with an absolute href enclosing the image, looking
	 * through inline wrappers like <span> or <strong>. Returns null if there
	 * is none.
	 */
	protected function getEnclosingExternalLink( DOMElement $node ): ?DOMElement {
		$anchor = $this->findEnclosingAnchor( $node );

		return $this->isExternalAnchor( $anchor ) ? $anchor : null;
	}

	/**
	 * Walks up from $node through inline wrappers (e.g. <span>, <strong>)
	 * looking for an enclosing <a>. Returns null if there is none.
	 */
	protected function findEnclosingAnchor( DOMNode $node ): ?DOMElement {
		$current = $node->parentNode;
		while ( $current instanceof DOMElement ) {
			if ( $current->nodeName === 'a' ) {
				return $current;
			}
			if ( !in_array( $current->nodeName, static::INLINE_WRAPPERS, true ) ) {
				return null;
			}
			$current = $current->parentNode;
		}
		return null;
	}

	/**
	 * MediaWiki does not render an img tag pointing to an external url.
	 * Builds the {{ExternalImage}} template text carrying the url, optional
	 * link target and the given params (e.g. height, width, align, ...),
	 * and registers the ExternalImage template page so it gets created on
	 * the target wiki.
	 *
	 * @param string[] $params Additional "name=value" template params
	 *  (e.g. attributes read off the image node), already formatted.
	 * @param string $urlText Already cleaned external image url.
	 * @param string $link Optional link target the image is wrapped in.
	 */
	protected function buildExternalImageTemplate( array $params, string $urlText, string $link = '' ): string {
		$templateParams = [];
		if ( $link !== '' ) {
			$templateParams[] = "link=$link";
		}
		$templateParams[] = "url=$urlText";

		foreach ( $params as $name => $value ) {
			$templateParams[] = "$name=$value";
		}

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			"ExternalImage"
		);

		return '{{ExternalImage|' . implode( '|', $templateParams ) . '}}';
	}

	/**
	 * Whether the given anchor has an href with a scheme (i.e. points to an
	 * external url, as opposed to a relative/internal link).
	 */
	private function isExternalAnchor( ?DOMElement $anchor ): bool {
		if ( $anchor === null || !$anchor->hasAttribute( 'href' ) ) {
			return false;
		}

		$parsedUrl = parse_url( $anchor->getAttribute( 'href' ) );

		return isset( $parsedUrl['scheme'] );
	}
}
