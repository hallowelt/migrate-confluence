<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the Confluence excerpt macro to a BlueSpice <excerpt-block> or <excerpt-inline> element.
 *
 * @see https://confluence.atlassian.com/doc/excerpt-macro-148062.html
 */
class ExcerptMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const EXCERPT_NAME_FALLBACK_PREFIX = 'excerpt-';

	/**
	 * Block-level content nodes. VE turns each into a paragraph/heading whose
	 * text runs and inline nodes are the leaves, so each is >= 1 leaf.
	 */
	private const LEAF_BLOCKS = [ 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'dt', 'dd' ];

	/**
	 * Structural containers with no direct content of their own: descend.
	 */
	private const CONTAINER_BLOCKS = [
		'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
		'ul', 'ol', 'li', 'dl', 'blockquote', 'div', 'section',
		'ac:layout', 'ac:layout-section', 'ac:layout-cell', 'ac:rich-text-body',
		'ac:task-list', 'ac:task', 'ac:task-body',
	];

	/**
	 * Standalone blocks that become a single leaf node.
	 */
	private const STANDALONE_BLOCKS = [ 'hr', 'figure' ];

	/**
	 * Inline nodes that become their own ve.dm node and therefore split a text
	 * run into separate leaves. Links / bold / italic are annotations in VE and
	 * do NOT split, so they are deliberately absent.
	 */
	private const INLINE_SPLITTERS = [ 'br', 'img', 'ac:image', 'ac:emoticon', 'time' ];

	private int $excerptMacroCount = 0;

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'excerpt';
	}

	public function process( DOMDocument $dom ): void {
		parent::process( $dom );
		$this->excerptMacroCount++;
	}

	/**
	 * @inheritDoc
	 *
	 * Pandoc strips unknown HTML elements like <excerpt-block> when converting to MediaWiki
	 * format. To preserve the tag, we insert text placeholders around the content here and
	 * restore the actual <excerpt-block> tag in the RestoreExcerptBlock postprocessor.
	 * Placeholders use pipe-separated values to avoid HTML attribute quote encoding issues.
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$hidden = '';
		$layout = 'block';
		$excerptName = "";
		$richTextBody = null;

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}

			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				$richTextBody = $childNode;
				continue;
			}

			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			$name = $childNode->getAttribute( 'ac:name' );
			if ( $name === 'hidden' ) {
				$hidden = trim( $childNode->nodeValue );
			}

			if ( $name === 'name' ) {
				$excerptName = trim( $childNode->nodeValue );
			}

			if ( $name === 'atlassian-macro-output-type' ) {
				$layout = strtolower( trim( $childNode->nodeValue ) );
			}
		}

		// BlueSpice's inline excerpt command rejects a body that resolves to more than
		// one ve.dm leaf node (fragment.getLeafNodes().length > 1). Confluence's inline
		// excerpt is more permissive, so an INLINE output-type may wrap several blocks.
		// If that is the case, force BLOCK layout, otherwise the migrated page throws
		// 'page-excerpts-ve-excerpt-inline-error-multi-node' when opened in VE.
		if ( $layout === 'inline' && $richTextBody !== null && $this->bodyIsMultiLeaf( $richTextBody ) ) {
			$layout = 'block';
		}

		$parent = $node->parentNode;

		if ( empty( $excerptName ) ) {
			$excerptName = $this->generateExcerptName();
		}

		$tag = $node->ownerDocument->createElement( 'excerpt-' . $layout );
		$tag->setAttribute( 'name', $excerptName );
		if ( $hidden ) {
			$tag->setAttribute( 'hidden', $hidden );
		}

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $childNode->childNodes ) as $bodyChild ) {
					$tag->appendChild( $bodyChild->cloneNode( true ) );
				}
			}
		}

		$parent->insertBefore(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder(
					$tag->ownerDocument->saveXML( $tag )
				),
				__METHOD__ ),
			$node );
		$parent->removeChild( $node );
	}

	/**
	 * Estimate whether the excerpt body will decompose into more than one ve.dm
	 * leaf node. Mirrors ve.Document.selectNodes( range, 'leaves' ), which
	 * descends through every branch node with children and stops at the deepest
	 * content pieces (text runs, inline nodes, opaque blocks).
	 *
	 * Heuristic: block structure survives pandoc -> wikitext -> Parsoid -> ve.dm
	 * faithfully; nested macros are the unpredictable part and are treated as a
	 * single opaque node unless they carry real block content. Bias is toward
	 * BLOCK on uncertainty, which is the safe direction.
	 *
	 * @param DOMElement $richTextBody The <ac:rich-text-body> of the excerpt macro
	 * @return bool
	 */
	private function bodyIsMultiLeaf( DOMElement $richTextBody ): bool {
		$count = 0;
		foreach ( $richTextBody->childNodes as $child ) {
			$count += $this->countLeaves( $child );
			if ( $count > 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param DOMNode $node
	 * @return int
	 */
	private function countLeaves( DOMNode $node ): int {
		if ( $node instanceof DOMText ) {
			return trim( $node->nodeValue ) === '' ? 0 : 1;
		}
		if ( !( $node instanceof DOMElement ) ) {
			return 0;
		}

		$name = strtolower( $node->nodeName );

		// Nested macro: opaque single node unless it wraps genuine block content.
		if ( $name === 'ac:structured-macro' || $name === 'ac:adf-extension' ) {
			return $this->hasBlockDescendant( $node ) ? $this->sumChildLeaves( $node ) : 1;
		}
		if ( in_array( $name, self::CONTAINER_BLOCKS, true ) ) {
			return $this->sumChildLeaves( $node );
		}
		if ( in_array( $name, self::LEAF_BLOCKS, true ) ) {
			return max( 1, $this->countInlineLeaves( $node ) );
		}
		if ( in_array( $name, self::STANDALONE_BLOCKS, true ) ) {
			return 1;
		}

		// Unknown element: descend if it wraps blocks, otherwise treat as inline.
		return $this->hasBlockDescendant( $node )
			? $this->sumChildLeaves( $node )
			: $this->countInlineLeaves( $node );
	}

	/**
	 * @param DOMNode $node
	 * @return int
	 */
	private function sumChildLeaves( DOMNode $node ): int {
		$sum = 0;
		foreach ( $node->childNodes as $child ) {
			$sum += $this->countLeaves( $child );
		}
		return $sum;
	}

	/**
	 * @param DOMNode $node
	 * @return bool
	 */
	private function hasBlockDescendant( DOMNode $node ): bool {
		foreach ( $node->childNodes as $child ) {
			if ( !( $child instanceof DOMElement ) ) {
				continue;
			}
			$name = strtolower( $child->nodeName );
			if ( in_array( $name, self::LEAF_BLOCKS, true ) || in_array( $name, self::CONTAINER_BLOCKS, true ) ) {
				return true;
			}
			if ( $this->hasBlockDescendant( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Leaves contributed by one content block: text runs split by inline nodes,
	 * approximated as (# inline splitter nodes) + (1 if any real text), floor 1.
	 *
	 * @param DOMElement $block
	 * @return int
	 */
	private function countInlineLeaves( DOMElement $block ): int {
		$splitters = 0;
		$hasText = false;
		$this->walkInline( $block, $splitters, $hasText );
		return max( 1, $splitters + ( $hasText ? 1 : 0 ) );
	}

	/**
	 * @param DOMNode $node
	 * @param int &$splitters
	 * @param bool &$hasText
	 * @return void
	 */
	private function walkInline( DOMNode $node, int &$splitters, bool &$hasText ): void {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMText ) {
				if ( trim( $child->nodeValue ) !== '' ) {
					$hasText = true;
				}
			} elseif ( $child instanceof DOMElement ) {
				if ( in_array( strtolower( $child->nodeName ), self::INLINE_SPLITTERS, true ) ) {
					$splitters++;
				}
				$this->walkInline( $child, $splitters, $hasText );
			}
		}
	}

	/**
	 * If no name given, generate one by convention: excerpt-<excerpt-count>
	 *
	 * @return string
	 */
	private function generateExcerptName(): string {
		return self::EXCERPT_NAME_FALLBACK_PREFIX . $this->excerptMacroCount;
	}
}
