<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixMultilineTable implements IPostprocessor {

	/** @var string[] */
	private const BLOCK_CHARS = [ '*', '#', ':', ';', '=' ];

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$blockChars = self::BLOCK_CHARS;
		$blockCharsRegex = '[' . preg_quote( implode( '', $blockChars ), '/' ) . ']';
		$wikiText = preg_replace_callback(
			'/\{\|(.*?)\|\}/s',
			static function ( $match ) use ( $blockChars, $blockCharsRegex ) {
				$tableText = $match[0];

				// Pandoc splits a styled cell that contains block-level content (e.g. <h5>)
				// into a bare cell marker on its own line followed by the attributes+content:
				//   |
				//   style="text-align: left;"| ===== heading =====
				// MediaWiki requires both on one line:
				//   | style="text-align: left;"| ===== heading =====
				$tableText = preg_replace(
					'/^([|!])[ \t]*\n([\w][\w-]*[ \t]*=)/m',
					'$1 $2',
					$tableText
				);

				$lines = explode( "\n", $tableText );

				$problematicLines = [];
				// Starting with index = 1 and ending with index < count()
				// will cut off table start ({|) and table end (|}).
				for ( $index = 1; $index < count( $lines ); $index++ ) {
					$line = $lines[$index];

					// Fix cell/header lines where the content starts with a wikitext
					// block construct (e.g. "| * list item" -> "|\n* list item")
					if ( preg_match( '/^[|!] ' . $blockCharsRegex . '/', $line ) ) {
						$problematicLines[] = $index;
						continue;
					}

					// Fix styled cell/header lines where the content after the attribute
					// separator starts with a block construct
					// (e.g. '| style="..."| = Heading' -> '| style="..."|' + "\n= Heading")
					if ( preg_match( '/^[|!] .+\| ' . $blockCharsRegex . '/', $line ) ) {
						$problematicLines[] = $index;
						continue;
					}

					// Only fix continuation lines that start with a wikitext
					// block construct that must be at the start of a line
					$firstChar = $line[0] ?? '';
					if ( !in_array( $firstChar, $blockChars ) ) {
						continue;
					}

					// Search backwards past blank lines to find the nearest cell start
					$cellLineIndex = $index - 1;
					while ( $cellLineIndex > 0 && trim( $lines[$cellLineIndex] ) === '' ) {
						$cellLineIndex--;
					}

					$cellLine = $lines[$cellLineIndex];
					if ( strpos( $cellLine, '|-' ) === 0 ) {
						continue;
					}
					if ( strpos( $cellLine, '|' ) !== 0
						&& strpos( $cellLine, '!' ) !== 0
					) {
						continue;
					}

					$problematicLines[] = $cellLineIndex;
				}

				$problematicLines = array_unique( $problematicLines );

				foreach ( $problematicLines as $problematicLine ) {
					$line = $lines[$problematicLine];

					// Pattern: "| attr| * content" or "! attr| * content" — split just
					// before the block content, preserving the attribute separator.
					if ( strpos( $line, '! ' ) === 0 ) {
						if ( preg_match( '/^(! .+?\| )(' . $blockCharsRegex . '.*)$/', $line, $m ) ) {
							$newLine = rtrim( $m[1] ) . "\n" . $m[2];
						} elseif ( preg_match( '/^(! .+?\| )(.+)$/', $line, $m ) ) {
							// Cell has attributes but content doesn't start with a block char;
							// keep the attribute on the cell line, move content to the next line.
							$newLine = rtrim( $m[1] ) . "\n" . $m[2];
						} else {
							$newLine = "!\n" . substr( $line, 2 );
						}
					} elseif ( strpos( $line, '!' ) === 0 ) {
						$newLine = "!\n" . substr( $line, 1 );
					} elseif ( strpos( $line, '| ' ) === 0 ) {
						if ( preg_match( '/^(\| .+?\| )(' . $blockCharsRegex . '.*)$/', $line, $m ) ) {
							$newLine = rtrim( $m[1] ) . "\n" . $m[2];
						} elseif ( preg_match( '/^(\| .+?\| )(.+)$/', $line, $m ) ) {
							// Cell has attributes but content doesn't start with a block char;
							// keep the attribute on the cell line, move content to the next line.
							$newLine = rtrim( $m[1] ) . "\n" . $m[2];
						} else {
							$newLine = "|\n" . substr( $line, 2 );
						}
					} elseif ( strpos( $line, '|' ) === 0 ) {
						$newLine = "|\n" . substr( $line, 1 );
					} else {
						continue;
					}

					$lines[$problematicLine] = $newLine;
				}
				return implode( "\n", $lines );
			},
			$wikiText
		);

		return $wikiText;
	}
}
