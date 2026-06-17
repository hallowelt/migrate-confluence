<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixMultilineTable implements IPostprocessor {

	/** @var string[] */
	private const BLOCK_CHARS = [ '*', '#', ':', ';', '=' ];
	private const TABLE_REGEX = '/\{\|(?:(?!\{\||\|\}).|(?R))*\|\}/s';

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$processedWikiText = $wikiText;

		do {
			$previousWikiText = $processedWikiText;
			$processedWikiText = preg_replace_callback(
				self::TABLE_REGEX,
				function ( array $match ): string {
					return $this->normalizeTable( $match[0] ) ?? $match[0];
				},
				$previousWikiText
			);

			if ( $processedWikiText === null ) {
				return $previousWikiText;
			}
		} while ( $processedWikiText !== $previousWikiText );

		return $processedWikiText;
	}

	private function normalizeTable( string $tableText ): string|null {
		$blockCharsRegex = '[' . preg_quote( implode( '', self::BLOCK_CHARS ), '/' ) . ']';

		// Remove blank lines between table rows and cells
		$tableText = preg_replace( '#(^\|-[^\n]*\R)(?:[\s\t]*\R)+(?=^\|-[^\n]*$)#m', '$1', $tableText );
		$tableText = preg_replace( '#(^![^\n]*\R)(?:[\s\t]*\R)+(?=^![^\n]*$)#m', '$1', $tableText );
		$tableText = preg_replace( '#(^\|[^\n]*\R)(?:[\s\t]*\R)+(?=^\|[^\n]*$)#m', '$1', $tableText );

		// Force nested tables to start on a new line
		$tableText = preg_replace( '/^(\h*[|!][^\n]*?)\h+(\{\|)/m', "$1\n$2", $tableText );

		// Force cell content starting with block chars to a new line
		$tableText = preg_replace(
			'/^([|!])\h+(' . $blockCharsRegex . '.*)$/m',
			"$1\n$2",
			$tableText
		);
		$tableText = preg_replace(
			'/^([|!]\h*[^\n]*?[|!])\h+(' . $blockCharsRegex . '.*)$/m',
			"$1\n$2",
			$tableText
		);

		// Pandoc splits a styled cell that contains block-level content (e.g. <h5>)
		// into a bare cell marker on its own line followed by the attributes+content:
		//   |
		//   style="text-align: left;"|
		//===== heading =====
		// MediaWiki requires both on one line but not the cell content:
		//   | style="text-align: left;"|
		//   ===== heading =====
		$tableText = preg_replace(
			'/^([|!])[\s\t]*\n([\w][\w-]*[\s\t]*=)/m',
			'$1 $2',
			$tableText
		);

		return $tableText;
	}

}
