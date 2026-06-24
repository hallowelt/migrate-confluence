<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * Fixes phantom list item wrappers produced by Pandoc.
 *
 * When an HTML <li> contains only a nested list (no text content), Pandoc
 * emits a "phantom" marker group on the first item of the nested list:
 *   "* ** Nested Item 1"  (followed by)
 *   "** Nested Item 2"
 *
 * These should become MediaWiki's indent-list notation:
 *   ":* Nested Item 1"
 *   ":* Nested Item 2"
 *
 * The conversion rule for a phantom chain A1 A2 ... An R Text
 * (where each Ai+1 is exactly one char longer than Ai and starts with Ai):
 *   result = A1[0..-2] + ":" * n + R[-1] + " " + Text
 *
 * Subsequent lines that carry the same real marker R are also converted
 * using the same replacement prefix, until a blank line or a non-matching
 * line resets the context.
 */
class FixEmptyListItemWrapper implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$lines = explode( "\n", $wikiText );
		$result = [];

		$activeRealMarker = null;
		$activeReplacementPrefix = null;
		$activeItemMarker = null;

		foreach ( $lines as $line ) {
			$phantomResult = $this->extractPhantomChain( $line );

			if ( $phantomResult !== null ) {
				$result[] = $phantomResult['replacement'];
				$activeRealMarker = $phantomResult['real_marker'];
				$activeReplacementPrefix = $phantomResult['replacement_prefix'];
				$activeItemMarker = $phantomResult['item_marker'];
			} elseif (
				$activeRealMarker !== null &&
				strlen( $line ) > strlen( $activeRealMarker ) &&
				str_starts_with( $line, $activeRealMarker . ' ' )
			) {
				$text = substr( $line, strlen( $activeRealMarker ) + 1 );
				$result[] = $activeReplacementPrefix . $activeItemMarker . ' ' . $text;
			} else {
				$activeRealMarker = null;
				$activeReplacementPrefix = null;
				$activeItemMarker = null;
				$result[] = $line;
			}
		}

		return implode( "\n", $result );
	}

	/**
	 * Detects and extracts a phantom-chain pattern from the beginning of a line.
	 *
	 * A phantom chain is two or more consecutive list-marker groups (each matching
	 * /^[*#]+$/) where every group is exactly one character longer than the
	 * previous and starts with the previous group as a prefix. The last group in
	 * the chain is the "real" marker; all earlier groups represent empty <li>
	 * wrappers.
	 *
	 * Returns null when no phantom chain is detected.
	 *
	 * @param string $line
	 * @return array{replacement: string, real_marker: string, replacement_prefix: string, item_marker: string}|null
	 */
	private function extractPhantomChain( string $line ): ?array {
		$parts = explode( ' ', $line );

		$groups = [];
		$textStartIdx = 0;

		foreach ( $parts as $i => $part ) {
			if ( preg_match( '/^[*#]+$/', $part ) ) {
				$groups[] = $part;
				$textStartIdx = $i + 1;
			} else {
				break;
			}
		}

		// Need at least 2 marker groups and at least one text word
		if ( count( $groups ) < 2 || $textStartIdx >= count( $parts ) ) {
			return null;
		}

		// Find the longest phantom chain: each group is one longer than the previous
		// and shares the previous group as a prefix
		$chainLength = 1;
		for ( $i = 1; $i < count( $groups ); $i++ ) {
			if (
				strlen( $groups[$i] ) === strlen( $groups[$i - 1] ) + 1 &&
				str_starts_with( $groups[$i], $groups[$i - 1] )
			) {
				$chainLength++;
			} else {
				break;
			}
		}

		// At least one phantom group (chainLength >= 2: one phantom + one real)
		if ( $chainLength < 2 ) {
			return null;
		}

		$phantomGroups = array_slice( $groups, 0, $chainLength - 1 );
		$realGroup = $groups[$chainLength - 1];

		// Any marker-like words beyond the chain belong to the text
		$extraGroups = array_slice( $groups, $chainLength );
		$textParts = array_slice( $parts, $textStartIdx );
		$text = implode( ' ', array_merge( $extraGroups, $textParts ) );

		// Build replacement: keep real prefix chars from first phantom group,
		// add one ':' per phantom level, then the last char of the real group
		$prefix = substr( $phantomGroups[0], 0, -1 );
		$colons = str_repeat( ':', count( $phantomGroups ) );
		$itemMarker = substr( $realGroup, -1 );

		return [
			'replacement' => $prefix . $colons . $itemMarker . ' ' . $text,
			'real_marker' => $realGroup,
			'replacement_prefix' => $prefix . $colons,
			'item_marker' => $itemMarker,
		];
	}
}
