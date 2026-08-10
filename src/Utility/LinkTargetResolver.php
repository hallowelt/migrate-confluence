<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMElement;
use Exception;

/**
 * Resolves Confluence <ac:link> elements to existing MediaWiki target title
 * strings.
 *
 * An <ac:link> wraps exactly one "resource identifier" (ri:*) element that
 * describes the actual target:
 *
 * - <ri:page ri:space-key="ABC" ri:content-title="Some page"/>
 * - <ri:blog-post ri:space-key="ABC" ri:content-title="Some post" ri:posting-day="2012/01/30"/>
 * - <ri:attachment ri:filename="happy.gif"/>          (optionally wrapping a <ri:page> for context)
 * - <ri:url ri:value="http://example.org/sample.gif"/>
 * - <ri:shortcut ri:key="jira" ri:parameter="ABC-123"/>
 * - <ri:user ri:userkey="…"/>
 * - <ri:space ri:space-key="TST"/>
 * - <ri:content-entity ri:content-id="123"/>
 *
 * The ri:space-key attribute is optional on page/blog-post/space elements;
 * if it is missing the link is relative to the space currently being
 * converted.
 *
 * <ac:link> may also carry an ac:anchor attribute, either alongside a
 * ri:* target (cross-page section link) or on its own (same-page section
 * link).
 *
 * This class only *resolves* links, it never decides whether a link is
 * "broken". If a target cannot be resolved to an existing wiki title,
 * getWikiPageTitleFromLinkElement() returns null so that the calling
 * macro/processor can decide how to handle the broken link (e.g. render a
 * placeholder built from generateConfluenceKey() and/or add a "broken link"
 * category).
 */
class LinkTargetResolver {

	/**
	 * Local (prefixed) tag names of ri:* elements that can occur as the
	 * direct target of an <ac:link>, or be passed directly to
	 * getWikiPageTitleFromLinkElement().
	 */
	private const SUPPORTED_TARGET_NODE_NAMES = [
		'ri:page',
		'ri:blog-post',
		'ri:attachment',
		'ri:url',
		'ri:shortcut',
		'ri:user',
		'ri:space',
		'ri:content-entity',
	];

	/** @var ConversionHelper */
	private ConversionHelper $conversionHelper;

	public function __construct(
		private readonly DBConversionDataLookup $dataLookup,
		private readonly int $defaultSpaceId,
	) {
		$this->conversionHelper = new ConversionHelper();
	}

	public function tryResolvePageLinkNode( DOMElement $node ): ?string {
		$anchor = $this->findAnchor( $node );
		$target = $this->findTargetNode( $node );

		if ( !$target ) {
			// No resolvable ri:* target at all. If there is at least an
			// anchor, this is a same-page anchor link.
			if ( $anchor !== '' ) {
				return '#' . $anchor;
			}

			return null;
		}

		try {
			$title = $this->resolvePageTarget( $target );
		} catch ( Exception $e ) {
			$title = null;
		}

		if ( $title === null ) {
			// Only ri:page/ri:blog-post targets get a placeholder; other
			// target types (url/user/space/shortcut/content-entity) are
			// either not page-like or not resolvable at all, so leave
			// them as unresolved (null).
			if ( $target->nodeName === 'ri:page' || $target->nodeName === 'ri:blog-post' ) {
				$spaceId = $this->ensureSpaceId( $target ) ?? $this->defaultSpaceId;
				$rawPageTitle = $target->getAttribute( 'ri:content-title' );
				$originalSpaceKey = $target->getAttribute( 'ri:space-key' );
				$title = $this->createPlaceholderPageLinkTarget( $spaceId, $rawPageTitle, $originalSpaceKey );
			} else {
				return null;
			}
		}

		if ( $anchor !== '' ) {
			$title .= '#' . $anchor;
		}

		return $title;
	}

	public function tryResolveFileLinkNode( DOMElement $node, string $confluenceParentTitle ): ?string {
		$anchor = $this->findAnchor( $node );
		$target = $this->findTargetNode( $node );

		if ( !$target ) {
			// No resolvable ri:* target at all. If there is at least an
			// anchor, this is a same-page anchor link.
			if ( $anchor !== '' ) {
				return '#' . $anchor;
			}

			return null;
		}

		if ( $target->nodeName !== 'ri:attachment' ) {
			return null;
		}

		try {
			$title = $this->resolveAttachment( $target, $confluenceParentTitle );
		} catch ( Exception $e ) {
			$title = null;
		}

		if ( $title === null ) {
			[ $spaceId, $rawPageTitle, $filename ] = $this->getAttachmentContext( $target, $confluenceParentTitle );
			if ( empty( $filename ) ) {
				// No filename at all, nothing to build a meaningful
				// placeholder from.
				return null;
			}

			$title = $this->createPlaceholderFileLinkTarget( $spaceId, $rawPageTitle, $filename );
		}

		if ( $anchor !== '' ) {
			$title .= '#' . $anchor;
		}

		return $title;
	}

	public static function isRedLink( string $title ): bool {
		return str_starts_with( $title, ConversionHelper::CONFLUENCE_PAGE_KEY_PREFIX ) ||
			str_starts_with( $title, ConversionHelper::CONFLUENCE_FILE_KEY_PREFIX );
	}

	/**
	 * Builds a stable placeholder string ("confluence key") for a page
	 * link target that could not be resolved to an existing wiki title.
	 * Used to leave debugging/manual-follow-up info in the converted
	 * output.
	 *
	 * Prefers the original ri:space-key attribute value (even if it
	 * didn't resolve to a known space) over re-deriving a key from
	 * $spaceId, so the placeholder distinguishes "no space-key given"
	 * (numeric space id form) from "an (invalid) space-key was given"
	 * (space key form) - useful for debugging/manual follow-up.
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @param string $originalSpaceKey The raw ri:space-key attribute
	 *  value from the source element, or an empty string if none was
	 *  given.
	 *
	 * @return string
	 */
	private function createPlaceholderPageLinkTarget(
		int $spaceId, string $rawPageTitle, string $originalSpaceKey = ''
	): string {
		if ( $originalSpaceKey !== '' ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $originalSpaceKey, $rawPageTitle );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $spaceId, $rawPageTitle );
	}

	private function createPlaceholderFileLinkTarget(
		int $spaceId,
		string $rawPageTitle,
		string $origFilename
	): string {
		$spaceKey = $this->dataLookup->getSpaceKeyFromSpaceId( $spaceId );
		if ( !empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluenceFileKeyFromSpaceKey( $spaceKey, $rawPageTitle, $origFilename );
		}

		return $this->conversionHelper->getConfluenceFileKeyFromSpaceId( $spaceId, $rawPageTitle, $origFilename );
	}

	/**
	 * Dispatches resolution of a ri:* target element to the appropriate
	 * lookup, based on its tag name.
	 *
	 * @param DOMElement $target
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function resolvePageTarget( DOMElement $target ): ?string {
		switch ( $target->nodeName ) {
			case 'ri:page':
				return $this->resolvePage( $target );

			case 'ri:blog-post':
				return $this->resolveBlogPost( $target );

			case 'ri:url':
				return $this->resolveUrl( $target );

			case 'ri:user':
				return $this->resolveUser( $target );

			case 'ri:space':
				return $this->resolveSpace( $target );

			case 'ri:shortcut':
			case 'ri:content-entity':
				// Not resolvable to an existing wiki target: shortcuts are
				// external application links, content entities are only
				// identified by an opaque Confluence content id.
				return null;

			default:
				return null;
		}
	}

	/**
	 * @param DOMElement $page
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function resolvePage( DOMElement $page ): ?string {
		$spaceId = $this->ensureSpaceId( $page );
		if ( $spaceId === null ) {
			return null;
		}

		$rawPageTitle = $page->getAttribute( 'ri:content-title' );

		return $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $rawPageTitle );
	}

	/**
	 * @param DOMElement $blogPost
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function resolveBlogPost( DOMElement $blogPost ): ?string {
		$spaceId = $this->ensureSpaceId( $blogPost );
		if ( $spaceId === null ) {
			return null;
		}

		$rawPageTitle = $blogPost->getAttribute( 'ri:content-title' );

		return $this->dataLookup->getWikiBlogPostTitleFromSpaceId( $spaceId, $rawPageTitle );
	}

	/**
	 * @param DOMElement $attachment
	 *
	 * @return string|null
	 */
	private function resolveAttachment( DOMElement $attachment, string $rawPageTitle ): ?string {
		[ $spaceId, $rawPageTitle, $filename ] = $this->getAttachmentContext( $attachment, $rawPageTitle );
		if ( empty( $filename ) ) {
			return null;
		}

		return $this->dataLookup->getWikiFileTitleFromSpaceId( $spaceId, $rawPageTitle, $filename );
	}

	/**
	 * Resolves the effective space id, page title and filename for a
	 * <ri:attachment> element, taking its optionally wrapped <ri:page>
	 * into account. Shared between the actual lookup and the placeholder
	 * fallback so both agree on the same target.
	 *
	 * @param DOMElement $attachment
	 * @param string $rawPageTitle Falls back to this if the attachment
	 *  doesn't wrap a <ri:page> with its own ri:content-title.
	 *
	 * @return array{0: int, 1: string, 2: string} [ $spaceId, $rawPageTitle, $filename ]
	 */
	private function getAttachmentContext( DOMElement $attachment, string $rawPageTitle ): array {
		$filename = $attachment->getAttribute( 'ri:filename' );
		$spaceId = $this->defaultSpaceId;

		// <ri:attachment> can optionally wrap a <ri:page> to point to an
		// attachment that lives on a different page (and/or space) than
		// the one currently being converted.
		foreach ( $attachment->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ri:page' ) {
				if ( $child->getAttribute( 'ri:content-title' ) !== '' ) {
					$rawPageTitle = $child->getAttribute( 'ri:content-title' );
				}
				$pageSpaceId = $this->ensureSpaceId( $child );
				if ( $pageSpaceId !== null ) {
					$spaceId = $pageSpaceId;
				}
				break;
			}
		}

		return [ $spaceId, $rawPageTitle, $filename ];
	}

	/**
	 * @param DOMElement $url
	 *
	 * @return string|null
	 */
	private function resolveUrl( DOMElement $url ): ?string {
		$value = $url->getAttribute( 'ri:value' );

		return $value !== '' ? $value : null;
	}

	/**
	 * @param DOMElement $user
	 *
	 * @return string|null
	 */
	private function resolveUser( DOMElement $user ): ?string {
		$userKey = $user->getAttribute( 'ri:userkey' );
		if ( empty( $userKey ) ) {
			return null;
		}

		$username = $this->dataLookup->getUsernameFromUserKey( $userKey );
		if ( $username === null ) {
			return null;
		}

		return 'User:' . $username;
	}

	/**
	 * @param DOMElement $space
	 *
	 * @return string|null
	 */
	private function resolveSpace( DOMElement $space ): ?string {
		$spaceKey = $space->getAttribute( 'ri:space-key' );
		if ( empty( $spaceKey ) ) {
			return null;
		}

		$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		if ( $spaceId === null ) {
			return null;
		}

		return $this->dataLookup->getSpaceMainPageWikiTitleForSpaceId( $spaceId );
	}

	/**
	 * Resolves the ri:space-key attribute of a ri:* element to a space id,
	 * falling back to the space currently being converted if the
	 * attribute is absent.
	 *
	 * @param DOMElement $node
	 *
	 * @return int|null Null if a ri:space-key was given but could not be
	 *  resolved to a known space.
	 */
	private function ensureSpaceId( DOMElement $node ): ?int {
		return $this->ensureSpaceIdFromKey( $node->getAttribute( 'ri:space-key' ) );
	}

	/**
	 * Resolves a (possibly empty) ri:space-key value to a space id,
	 * falling back to the space currently being converted if the key is
	 * empty.
	 *
	 * @param string $spaceKey
	 *
	 * @return int|null Null if a non-empty $spaceKey was given but could
	 *  not be resolved to a known space.
	 */
	private function ensureSpaceIdFromKey( string $spaceKey ): ?int {
		if ( $spaceKey === '' ) {
			return $this->defaultSpaceId;
		}

		return $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return string ac:anchor attribute value, or an empty string if
	 *  none is present (e.g. because $node isn't an <ac:link>).
	 */
	private function findAnchor( DOMElement $node ): string {
		if ( $node->nodeName !== 'ac:link' ) {
			return '';
		}

		return $node->getAttribute( 'ac:anchor' );
	}

	/**
	 * Finds the ri:* element describing the link target: either $node
	 * itself, or - if $node is an <ac:link> - its first supported ri:*
	 * child element.
	 *
	 * @param DOMElement $node
	 *
	 * @return DOMElement|null
	 */
	private function findTargetNode( DOMElement $node ): ?DOMElement {
		if ( in_array( $node->nodeName, self::SUPPORTED_TARGET_NODE_NAMES, true ) ) {
			return $node;
		}

		if ( $node->nodeName !== 'ac:link' ) {
			return null;
		}

		foreach ( $node->childNodes as $child ) {
			if (
				$child instanceof DOMElement &&
				in_array( $child->nodeName, self::SUPPORTED_TARGET_NODE_NAMES, true )
			) {
				return $child;
			}
		}

		return null;
	}
}
