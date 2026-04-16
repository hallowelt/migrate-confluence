<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Moves any <ac:structured-macro> that is a direct child of a heading element
 * (<h1>–<h6>) to immediately after that heading.
 *
 * Block-level macros inside headings cause pandoc to emit things like
 *   = {{MacroStart|...}}{{MacroEnd}} =
 * which MediaWiki cannot render correctly.
 *
 * If the heading contains only whitespace after the macro is removed, the
 * heading itself is also removed to avoid generating an empty heading.
 */
class HoistMacroFromHeading implements IProcessor {

	/** @var string[] */
	private const HEADING_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
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
		// Collect only direct-child structured-macro elements
		$macros = [];
		foreach ( $heading->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->localName === 'structured-macro' ) {
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

		// Remove the heading if nothing meaningful remains
		if ( trim( $heading->textContent ) === '' ) {
			$headingParent->removeChild( $heading );
		}
	}
}
