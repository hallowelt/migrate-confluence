<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * When a wikitable is nested inside a template, every `|` in the table is
 * misinterpreted as a template-parameter separator.
 * This postprocessor replaces those pipe characters with `{{!}}` so that
 * MediaWiki renders them correctly.
 *
 * It runs while `###BREAK###` markers are still present (before they are
 * replaced with newlines in `postprocessWikiText`), because the body-parameter
 * boundary is identified by `|body = ###BREAK###` when a body parameter is
 * present.
 */
class EscapePipesInTemplateBody implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$offset = 0;
		$result = '';
		$len = strlen( $wikiText );

		$pos = strpos( $wikiText, '{{', $offset );
		while ( $pos !== false ) {
			$result .= substr( $wikiText, $offset, $pos - $offset );

			$closePos = $this->findMatchingClose( $wikiText, $pos, $len );
			if ( $closePos === -1 ) {
				$result .= substr( $wikiText, $pos );
				$offset = $len;
				break;
			}

			$templateContent = substr( $wikiText, $pos, $closePos + 2 - $pos );
			$result .= $this->processTemplate( $templateContent );
			$offset = $closePos + 2;
			$pos = strpos( $wikiText, '{{', $offset );
		}

		$result .= substr( $wikiText, $offset );
		return $result;
	}

	/**
	 * Walk forward from $start tracking `{{`/`}}` nesting depth and return the
	 * position of the matching `}}`, or -1 if not found.
	 */
	private function findMatchingClose( string $text, int $start, int $len ): int {
		$depth = 0;
		$i = $start;
		while ( $i < $len ) {
			if ( substr( $text, $i, 2 ) === '{{' ) {
				$depth++;
				$i += 2;
			} elseif ( $text[$i] === '}' ) {
				// A run of closing braces may be longer than 2, e.g. when a
				// wikitable's `|}` close is immediately followed by the
				// template's `}}` close (`|}}}`). An odd-length run starts
				// with a stray literal `}` that isn't part of any pair, so
				// pairs are matched against the tail of the run.
				$runStart = $i;
				$runLen = 0;
				while ( $i < $len && $text[$i] === '}' ) {
					$runLen++;
					$i++;
				}
				$pairStart = $runStart;
				if ( $runLen % 2 !== 0 ) {
					$pairStart++;
					$runLen--;
				}
				$pairs = intdiv( $runLen, 2 );
				for ( $p = 0; $p < $pairs; $p++ ) {
					$depth--;
					if ( $depth === 0 ) {
						return $pairStart + $p * 2;
					}
				}
			} else {
				$i++;
			}
		}
		return -1;
	}

	/**
	 * If the template contains a wikitable, escape the table's pipe characters
	 * with `{{!}}`.
	 */
	private function processTemplate( string $template ): string {
		// Locate the body parameter marker, with or without a real linebreak.
		if ( preg_match( '/\|body\s*=\s*###BREAK###\n?/s', $template, $matches, PREG_OFFSET_CAPTURE ) ) {
			$matchStart = $matches[0][1];
			$matchText = $matches[0][0];
			$markerEnd = $matchStart + strlen( $matchText );
			// Ensure the marker always reads `body =`, regardless of the original spacing.
			$normalizedMarker = preg_replace( '/body\s*=/', 'body =', $matchText, 1 );
			$before = substr( $template, 0, $matchStart ) . $normalizedMarker;
			$body = substr( $template, $markerEnd, strlen( $template ) - $markerEnd - 2 );
			if ( strpos( $body, '{|' ) === false ) {
				return $template;
			}

			return $before . $this->escapeWikitablePipes( $body ) . '}}';
		}

		$tableStart = strpos( $template, '{|' );
		if ( $tableStart === false ) {
			return $template;
		}

		return substr( $template, 0, $tableStart ) .
			$this->escapeWikitablePipes( substr( $template, $tableStart, strlen( $template ) - $tableStart - 2 ) ) .
			'}}';
	}

	/**
	 * Replace pipe characters that are part of wikitable syntax with `{{!}}`.
	 *
	 * Rules:
	 *  - `{|`  (table open) — becomes `{{(!}}`; a plain `{` in front of `{{!}}`
	 *    would form an ambiguous `{{{` sequence that MediaWiki misparses as
	 *    the start of a `{{{parameter}}}` placeholder, so the dedicated
	 *    `{{(!}}` magic word/template is used instead.
	 *  - `||`  (inline cell separator) — both pipes become `{{!}}{{!}}`
	 *  - `|`   at the start of a line (row sep, cell, caption, close) — becomes `{{!}}`
	 */
	private function escapeWikitablePipes( string $body ): string {
		$lines = explode( "\n", $body );
		foreach ( $lines as &$line ) {
			// The table open may be preceded by whitespace, e.g. when it follows
			// the body marker on the same line; that whitespace must be kept.
			if ( preg_match( '/^(\s*)\{\|/', $line, $tableMatches ) ) {
				$line = $tableMatches[1] . '{{(!}}' . substr( $line, strlen( $tableMatches[0] ) );
				continue;
			}
			// Replace inline cell separator first so the leading-pipe check still works.
			$line = str_replace( '||', '{{!}}{{!}}', $line );
			if ( strpos( $line, '|' ) === 0 ) {
				$line = '{{!}}' . substr( $line, 1 );
			}
		}
		return implode( "\n", $lines );
	}

}
