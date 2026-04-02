<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * When a wikitable is nested inside a template's `body` parameter, every `|`
 * in the table is misinterpreted as a template-parameter separator.
 * This postprocessor replaces those pipe characters with `{{!}}` so that
 * MediaWiki renders them correctly.
 *
 * It runs while `###BREAK###` markers are still present (before they are
 * replaced with newlines in `postprocessWikiText`), because the body-parameter
 * boundary is identified by `|body = ###BREAK###`.
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
			} elseif ( substr( $text, $i, 2 ) === '}}' ) {
				$depth--;
				if ( $depth === 0 ) {
					return $i;
				}
				$i += 2;
			} else {
				$i++;
			}
		}
		return -1;
	}

	/**
	 * If the template has a `body` parameter whose value contains a wikitable,
	 * escape the table's pipe characters with `{{!}}`.
	 */
	private function processTemplate( string $template ): string {
		// Locate the body parameter marker: "|body = ###BREAK###\n"
		if ( !preg_match( '/\|body\s*=\s*###BREAK###\n/s', $template, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $template;
		}

		$markerEnd = $matches[0][1] + strlen( $matches[0][0] );
		$before = substr( $template, 0, $markerEnd );
		// Everything between the body marker and the closing }} is body content.
		// The closing }} was already verified by findMatchingClose.
		$body = substr( $template, $markerEnd, strlen( $template ) - $markerEnd - 2 );
		$closing = '}}';

		if ( strpos( $body, '{|' ) === false ) {
			return $template;
		}

		return $before . $this->escapeWikitablePipes( $body ) . $closing;
	}

	/**
	 * Replace pipe characters that are part of wikitable syntax with `{{!}}`.
	 *
	 * Rules:
	 *  - `{|`  (table open)  — left as-is; the `{` makes it unambiguous
	 *  - `||`  (inline cell separator) — both pipes become `{{!}}{{!}}`
	 *  - `|`   at the start of a line (row sep, cell, caption, close) — becomes `{{!}}`
	 */
	private function escapeWikitablePipes( string $body ): string {
		$lines = explode( "\n", $body );
		foreach ( $lines as &$line ) {
			if ( strpos( $line, '{|' ) === 0 ) {
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
