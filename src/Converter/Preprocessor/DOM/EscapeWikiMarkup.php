<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM;

use DOMDocument;
use DOMNode;
use DOMText;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Plain text from Confluence that happens to look like wikitext markup (e.g.
 * "[[wiki]]", "{{tpl}}" or "''quoted''") would be live markup in MediaWiki, as
 * pandoc passes it through unchanged. This preprocessor wraps such text in
 * <nowiki>.
 *
 * It has to run before any processor, so that it only ever sees the original
 * Confluence text and never wikitext emitted by the converter itself (template
 * calls etc.). The <nowiki> is hidden behind a placeholder, so that pandoc
 * leaves it alone.
 *
 * Text inside <pre>, <code> and Confluence parameter or plain text bodies is
 * left alone: these are either not parsed by MediaWiki or consumed as raw data
 * by the macro processors.
 */
class EscapeWikiMarkup implements IDomPreprocessor {

	/** Character sequences that MediaWiki would interpret inside a text. */
	private const MARKUP_PATTERN = "/\\[\\[|\\]\\]|\\{\\{|\\}\\}|''|~~~|__[A-Z]+__/";

	/** Elements (ignoring any namespace prefix) whose text must not be touched. */
	private const SKIPPED_ELEMENTS = [
		'pre', 'code', 'plain-text-body', 'parameter', 'plain-text-link-body'
	];

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function preprocess( DOMDocument $dom ): void {
		$textNodes = [];
		foreach ( ( new DOMXPath( $dom ) )->query( '//text()' ) as $textNode ) {
			if (
				$textNode instanceof DOMText &&
				preg_match( self::MARKUP_PATTERN, $textNode->data ) &&
				!$this->isInSkippedElement( $textNode )
			) {
				$textNodes[] = $textNode;
			}
		}

		foreach ( $textNodes as $textNode ) {
			// Whitespace stays outside, as MediaWiki would show it inside <nowiki> verbatim
			preg_match( '/^(\s*)(.*?)(\s*)$/s', $textNode->data, $parts );
			[ , $before, $content, $after ] = $parts;

			$textNode->data = $before
				. $this->placeholderManager->getPlaceholder( "<nowiki>$content</nowiki>" )
				. $after;
		}
	}

	/**
	 * @param DOMNode $node
	 * @return bool
	 */
	private function isInSkippedElement( DOMNode $node ): bool {
		for ( $parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode ) {
			$name = preg_replace( '/^.*:/', '', $parent->nodeName );
			if ( in_array( $name, self::SKIPPED_ELEMENTS, true ) ) {
				return true;
			}
		}
		return false;
	}
}
