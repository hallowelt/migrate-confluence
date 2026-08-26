<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * This resolver flattens possibly nested marker pairs into a single, valid,
 * top-level HTML comment
 */
class HtmlCommentMarkerResolver {

	public const OPEN_MARKER = '###HTMLCOMMENTOPEN###';

	public const CLOSE_MARKER = '###HTMLCOMMENTCLOSE###';

	public static function resolve( string $text ): string {
		$parts = preg_split(
			'/(' . preg_quote( self::OPEN_MARKER, '/' ) . '|' . preg_quote( self::CLOSE_MARKER, '/' ) . ')/',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		$resolved = '';
		$depth = 0;
		foreach ( $parts as $part ) {
			if ( $part === self::OPEN_MARKER ) {
				if ( $depth === 0 ) {
					$resolved .= '<!-- ';
				}
				$depth++;
				continue;
			}

			if ( $part === self::CLOSE_MARKER ) {
				if ( $depth > 0 ) {
					$depth--;
					if ( $depth === 0 ) {
						$resolved .= ' -->';
					}
				}
				continue;
			}

			$resolved .= $part;
		}

		// Unbalanced markers should not happen, but close a dangling comment defensively
		// instead of leaving invisible marker debris behind.
		if ( $depth > 0 ) {
			$resolved .= ' -->';
		}

		return $resolved;
	}
}
