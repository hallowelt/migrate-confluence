<?php

namespace HalloWelt\MigrateConfluence\Utility\WikiTextWcagSanitizer;

class Headings {

	private const CATEGORY_HEADING_OERDER = 'Heading_order';
	private const CATEGORY_HEADING_OERDER_FIXED = 'Heading_order_fixed';

	/**
	 * Fix the order of headings and add a maintenance category
	 *
	 * @param string $wikitext
	 * @return string
	 */
	public function sanitize( string $wikitext ): string {
		$goodHeadingOrder = $this->isHeadingOrderGood( $wikitext );

		if ( !$goodHeadingOrder ) {
			$wikitext = $this->sanitizeHeadings( $wikitext );
			$wikitext .= "\n\n[[Category:WCAG/Heading_order]]";
		}

		return $wikitext;
	}

	public function isHeadingOrderGood( string $wikitext ): bool {
		$previousOriginalLevel = null;
		$previousLevel = null;

		preg_match_all( '/^(={1,6})[ \t]*(.*?)[ \t]*(={1,6})[ \t]*\r?$/m', $wikitext, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			if ( strlen( $match[1] ) !== strlen( $match[3] ) ) {
				continue;
			}

			$originalLevel = strlen( $match[1] );
			$level = $originalLevel;

			if ( $previousLevel === null ) {
				$level = 2;
			} elseif ( $originalLevel > $previousOriginalLevel ) {
				$level = $previousLevel + 1;
			} elseif ( $originalLevel < $previousOriginalLevel ) {
				$level = $previousLevel - ( $previousOriginalLevel - $originalLevel );
			} else {
				$level = $previousLevel;
			}

			$level = min( 6, max( 2, $level ) );

			if ( $level !== $originalLevel ) {
				return false;
			}

			$previousOriginalLevel = $originalLevel;
			$previousLevel = $level;
		}

		return true;
	}

	private function sanitizeHeadings( string $wikitext ): string {
		$previousOriginalLevel = null;
		$previousLevel = null;

		$sanitized = preg_replace_callback(
			'/^(={1,6})[ \t]*(.*?)[ \t]*(={1,6})[ \t]*\r?$/m',
			static function ( array $matches ) use ( &$previousOriginalLevel, &$previousLevel ): string {
				if ( strlen( $matches[1] ) !== strlen( $matches[3] ) ) {
					return $matches[0];
				}

				$originalLevel = strlen( $matches[1] );
				$level = $originalLevel;

				if ( $previousLevel === null ) {
					$level = 2;
				} elseif ( $originalLevel > $previousOriginalLevel ) {
					$level = $previousLevel + 1;
				} elseif ( $originalLevel < $previousOriginalLevel ) {
					$level = $previousLevel - ( $previousOriginalLevel - $originalLevel );
				} else {
					$level = $previousLevel;
				}

				$level = min( 6, max( 2, $level ) );
				$previousOriginalLevel = $originalLevel;
				$previousLevel = $level;

				return str_repeat( '=', $level ) . ' ' . trim( $matches[2] ) . ' ' . str_repeat( '=', $level );
			},
			$wikitext
		);

		if ( !is_string( $sanitized ) ) {
			return $wikitext;
		}

		return $sanitized;
	}
}
