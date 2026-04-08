<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixLineBreakInHeadings implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$callback = static function ( $matches ) {
			$openTag = $matches[1];
			$content = $matches[2];
			$closeTag = $matches[3];
			// Strip <br /> variants and collapse newlines to spaces.
			$content = str_replace( [ '<br />', '<br/>', "\r\n", "\n", "\r" ], ' ', $content );
			// Collapse multiple spaces and trim.
			$content = trim( preg_replace( '/ {2,}/', ' ', $content ) );
			return "$openTag $content $closeTag";
		};

		for ( $heading = 1; $heading <= 6; $heading++ ) {
			// Single-line headings: == heading <br /> ==
			$wikiText = preg_replace_callback(
				$this->buildSingleLineRegEx( $heading ),
				$callback,
				$wikiText
			);
			// Multi-line headings: == heading <br />\n<br />\n== (closing tag on its own line)
			$wikiText = preg_replace_callback(
				$this->buildMultiLineRegEx( $heading ),
				$callback,
				$wikiText
			);
		}
		return $wikiText;
	}

	/**
	 * Matches headings where the <br /> and the closing tag are on the same line.
	 * Example: == heading text <br /> ==
	 *
	 * @param int $level
	 * @return string
	 */
	private function buildSingleLineRegEx( int $level ): string {
		$tag = str_repeat( '=', $level );
		// '(?!=)' prevents '===' from matching '===='.
		// '[^\n]*' keeps matching within a single line only.
		return "#^($tag(?!=))([^\n]*<br\s*/?>[^\n]*)($tag)\s*$#m";
	}

	/**
	 * Matches headings where <br /> is followed by a real newline and the closing
	 * tag appears on its own line.
	 * Example: === heading:<br />\n<br />\n===
	 *
	 * Uses {0,3} to bound repetition and prevent catastrophic backtracking.
	 *
	 * @param int $level
	 * @return string
	 */
	private function buildMultiLineRegEx( int $level ): string {
		$tag = str_repeat( '=', $level );
		// Content spans lines, but the closing tag must be alone on its line
		// (just $tag + optional whitespace), preventing matches across sections.
		// {0,3} limits span to avoid catastrophic backtracking.
		return "#^($tag(?!=))([^\n]*<br\s*/?>[^\n]*(?:\n[^\n]+){0,3})\n($tag)\s*$#m";
	}
}
