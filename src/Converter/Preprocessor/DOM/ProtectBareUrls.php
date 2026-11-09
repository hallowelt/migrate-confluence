<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Pandoc 3.11+ wraps bare URLs in plain text in <nowiki>, which stops MediaWiki
 * from turning them into links. This preprocessor hides them behind a
 * placeholder, so that they reach the wiki text unchanged and are auto-linked
 * there.
 *
 * It has to run before any processor, so that it only ever sees the original
 * Confluence text. URLs in links, <pre>, <code> and Confluence parameter or
 * plain text bodies are left alone.
 */
class ProtectBareUrls implements IDomPreprocessor {

	/** Any URL with a scheme followed by "//", which is what pandoc wraps. */
	private const URL_PATTERN = '#[a-z][a-z0-9+.-]*://[^\s<>"\[\]]+#i';

	/** Elements (ignoring any namespace prefix) whose text must not be touched. */
	private const SKIPPED_ELEMENTS = [
		'a', 'pre', 'code', 'plain-text-body', 'parameter', 'plain-text-link-body'
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
				preg_match( self::URL_PATTERN, $textNode->data ) &&
				!$this->isInSkippedElement( $textNode )
			) {
				$textNodes[] = $textNode;
			}
		}

		foreach ( $textNodes as $textNode ) {
			$textNode->data = preg_replace_callback(
				self::URL_PATTERN,
				function ( array $matches ): string {
					// Like MediaWiki, leave sentence punctuation and unbalanced ")" out of the URL
					preg_match( '/^(.*?)([.,;:!?\')]*)$/s', $matches[0], $parts );
					[ , $url, $trailing ] = $parts;
					while (
						str_starts_with( $trailing, ')' ) && substr_count( $url, '(' ) > substr_count( $url, ')' )
					) {
						$url .= ')';
						$trailing = substr( $trailing, 1 );
					}
					// Pandoc escapes "&" in wikitext, too; this avoids accidental HTML entities
					return $this->placeholderManager->getPlaceholder( str_replace( '&', '&amp;', $url ) ) . $trailing;
				},
				$textNode->data
			);
		}
	}

	/**
	 * @param DOMNode $node
	 * @return bool
	 */
	private function isInSkippedElement( DOMNode $node ): bool {
		for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
			$name = preg_replace( '/^.*:/', '', $parent->nodeName );
			if ( in_array( $name, self::SKIPPED_ELEMENTS, true ) ) {
				return true;
			}
		}
		return false;
	}
}
