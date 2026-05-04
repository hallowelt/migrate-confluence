<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\dom;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;

/**
 * Moves any <ac:structured-macro> or <ac:macro> that is a direct child of a heading element
 * (<h1>–<h6>) to immediately after that heading.
 *
 * Block-level macros inside headings cause pandoc to emit things like
 *   = {{MacroStart|...}}{{MacroEnd}} =
 * which MediaWiki cannot render correctly.
 *
 * If the heading contains only whitespace after the macro is removed, the
 * heading itself is also removed to avoid generating an empty heading.
 */
class HoistMacroFromHeading implements IDomPreprocessor {

	/** @var string[] */
	private const HEADING_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	/** @var string[] */
	private const MACRO_TAGS = [ 'structured-macro', 'macro' ];

	/**
	 * @inheritDoc
	 */
	public function preprocess( DOMDocument $dom ): void {
		foreach ( self::HEADING_TAGS as $tag ) {
			$headings = $dom->getElementsByTagName( $tag );

			// Snapshot to avoid mutating a live NodeList during iteration
			$headingList = [];
			foreach ( $headings as $heading ) {
				$headingList[] = $heading;
			}

			foreach ( $headingList as $heading ) {
				$this->hoistFromHeading( $heading );
			}
		}
	}

	/**
	 * @param DOMElement $heading
	 * @return void
	 */
	private function hoistFromHeading( DOMElement $heading ): void {
		// Collect only direct-child structured-macro and macro elements
		$macros = [];
		foreach ( $heading->childNodes as $child ) {
			if ( $child instanceof DOMElement && in_array( $child->localName, self::MACRO_TAGS ) ) {
				$macros[] = $child;
			}
		}

		if ( empty( $macros ) ) {
			return;
		}

		$headingParent = $heading->parentNode;
		$insertBefore = $heading->nextSibling;

		foreach ( $macros as $macro ) {
			$heading->removeChild( $macro );

			if ( $insertBefore !== null ) {
				$headingParent->insertBefore( $macro, $insertBefore );
			} else {
				$headingParent->appendChild( $macro );
			}

			// Keep subsequent macros ordered: next one goes after the one just inserted
			$insertBefore = $macro->nextSibling;
		}

		// Trim whitespace from remaining text nodes
		foreach ( $heading->childNodes as $child ) {
			if ( $child->nodeType === XML_TEXT_NODE ) {
				$child->nodeValue = trim( $child->nodeValue );
			}
		}

		// Remove the heading if nothing meaningful remains
		if ( trim( $heading->textContent ) === '' ) {
			$headingParent->removeChild( $heading );
		}
	}
}
