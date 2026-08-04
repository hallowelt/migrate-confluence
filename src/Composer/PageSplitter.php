<?php

namespace HalloWelt\MigrateConfluence\Composer;

/**
 * Splits oversized wikitext into chunks that fit within the visual editor's
 * size limit, breaking preferably at heading boundaries.
 */
class PageSplitter {

	/** @var int Approx. 512 KiB per chunk */
	private const CHUNK_SIZE = 524288;

	/**
	 * Split wikitext into chunks of at most CHUNK_SIZE bytes.
	 * Returns a single-element array when no split is needed.
	 *
	 * @param string $wikiText
	 * @return string[]
	 */
	public static function split( string $wikiText ): array {
		if ( strlen( $wikiText ) <= self::CHUNK_SIZE ) {
			return [ $wikiText ];
		}

		// Collect byte offsets of every line-starting heading (== ... ==).
		// We store the position of the '=' character (the '\n' before it is
		// kept as the trailing newline of the preceding chunk).
		$candidates = [];
		if ( preg_match_all( '/(?<=\n)(?==)/m', $wikiText, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $m ) {
				$candidates[] = $m[1];
			}
		}

		return self::buildChunks( $wikiText, $candidates );
	}

	/**
	 * Greedily assign text to chunks, using $candidates as preferred split
	 * points.  Falls back to the last double-newline before the limit, then
	 * to a hard byte boundary.
	 *
	 * @param string $text
	 * @param int[] $candidates Sorted byte offsets (heading starts).
	 * @return string[]
	 */
	private static function buildChunks( string $text, array $candidates ): array {
		$chunks = [];
		$start = 0;
		$len = strlen( $text );
		$ci = 0;
		$cn = count( $candidates );

		while ( $start < $len ) {
			$end = $start + self::CHUNK_SIZE;

			if ( $end >= $len ) {
				$chunks[] = substr( $text, $start );
				break;
			}

			// Advance past candidates already consumed.
			while ( $ci < $cn && $candidates[$ci] <= $start ) {
				$ci++;
			}

			// Pick the last candidate that still fits within the chunk.
			$splitAt = null;
			for ( $j = $ci; $j < $cn && $candidates[$j] < $end; $j++ ) {
				$splitAt = $candidates[$j];
			}

			if ( $splitAt === null ) {
				// No heading: fall back to last blank line within the window.
				$window = substr( $text, $start, self::CHUNK_SIZE );
				$lastNl = strrpos( $window, "\n\n" );
				if ( $lastNl !== false && $lastNl > 0 ) {
					$splitAt = $start + $lastNl + 2;
				} else {
					// Hard byte split: back up to the start of a UTF-8 character
					// so we never cut inside a multi-byte sequence.
					$splitAt = $end;
					while ( $splitAt > $start && ( ord( $text[$splitAt] ) & 0xC0 ) === 0x80 ) {
						$splitAt--;
					}
				}
			}

			$chunks[] = substr( $text, $start, $splitAt - $start );
			$start = $splitAt;
		}

		return $chunks;
	}

	/**
	 * Given a base page title and a number of parts, return all part titles.
	 * Part 1 keeps the original title; later parts get a "/N" suffix.
	 *
	 * @param string $baseTitle
	 * @param int $partCount
	 * @return string[]
	 */
	public static function buildPartTitles( string $baseTitle, int $partCount ): array {
		$titles = [ $baseTitle ];
		for ( $i = 2; $i <= $partCount; $i++ ) {
			$titles[] = $baseTitle . '/' . $i;
		}
		return $titles;
	}

	/**
	 * Wrap a chunk with predecessor/successor navigation links and the
	 * Overly_long_page category.
	 *
	 * @param string $chunk Raw wikitext chunk.
	 * @param int $index 0-based index of this part.
	 * @param string[] $titles All part titles (from buildPartTitles).
	 * @return string
	 */
	public static function addNavigation( string $chunk, int $index, array $titles ): string {
		$total = count( $titles );

		$header = '';
		if ( $index > 0 ) {
			$prevTitle = $titles[$index - 1];
			$prevLabel = $index === 1 ? $prevTitle : 'Part ' . $index;
			$header = "← [[$prevTitle|$prevLabel]]\n\n";
		}

		$footer = '';
		if ( $index < $total - 1 ) {
			$nextTitle = $titles[$index + 1];
			$nextLabel = 'Part ' . ( $index + 2 );
			$footer = "\n\n[[$nextTitle|$nextLabel]] →";
		}

		return $header . rtrim( $chunk ) . $footer . "\n[[Category:Overly_long_page]]";
	}
}
