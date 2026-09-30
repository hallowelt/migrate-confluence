<?php

namespace HalloWelt\MigrateConfluence\Utility\WikiTextWcagSanitizer;

class Tables {

	private const CATEGORY_TABLE_CAPTION = 'Table_missing_caption';

	/**
	 * Adding a caption to a table if not exists.
	 * The $label variable is used to create the caption with a number
	 * e.g.
	 * |+ Table 1
	 *
	 * And add a maintenance category if no caption was set.
	 *
	 * @param string $wikitext
	 * @param string $label Label for caption. E.g. "Table" for |+ Table 1
	 * @return string
	 */
	public function sanitize( string $wikitext, string $label = "Table" ): string {
		$tables = $this->extractWikiTables( $wikitext );

		$tableWithoudCaption = false;
		for ( $i = 0; $i < count( $tables ); $i++ ) {
			$table = $tables[$i];
			if ( $this->tableHasCaption( $table ) ) {
				continue;
			}
			$this->addTableCaption( $wikitext, $label, $table, $i + 1 );
			$tableWithoudCaption = true;
		}

		if ( !$tableWithoudCaption ) {
			$category = "[[Category:Table_without_caption]]";
			$wikitext .= "\n$category\n";
		}

		return $wikitext;
	}

	private function extractWikiTables( string $wikitext ): array {
		$data = [];
		$open = [];
		$close = [];

		// Find opening table syntax
		preg_match_all(
			'/(\{\|)/m',
			$wikitext,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return [];
		}

		foreach ( $matches[0] as $match ) {
			$open[] = $match[1];
		}

		// Find closing table syntax
		preg_match_all(
			'/(\|\})/m',
			$wikitext,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		if ( empty( $matches ) || empty( $matches[0] ) ) {
			return [];
		}

		foreach ( $matches[0] as $match ) {
			$close[] = $match[1];
		}

		$sections = [];

		// Mark all start and end lines as sections.
		foreach ( $open as $line ) {
			$sections[] = [
				'pos' => $line,
				'type' => 'start',
			];
		}

		foreach ( $close as $line ) {
			$sections[] = [
				'pos' => $line,
				'type' => 'end',
			];
		}

		// Process syntax in document order.
		// If start and end occur on the same line, process start first.
		usort( $sections, static function ( $a, $b ) {
			if ( $a['pos'] === $b['pos'] ) {
				return $a['type'] === 'start' ? -1 : 1;
			}

			return $a['pos'] <=> $b['pos'];
		} );

		$stack = [];
		$tables = [];
		foreach ( $sections as $section ) {
			if ( $section['type'] === 'start' ) {
				$stack[] = $section['pos'];
				continue;
			}
			// var_dump( $stack );
			if ( $section['type'] === 'end' ) {
				// the closest table start
				$start = array_pop( $stack );
				$end = $section['pos'];
				$tables[] = [
					'start' => $start,
					'end' => $end,
					'text' => substr( $wikitext, $start, $end - $start )
				];
			}
		}

		// Nested tables are pleaced before the container table in the array.
		// For better numbering we want to have the container table first.
		usort( $tables, static function ( array $a, array $b ): int {
			if ( $a['start'] !== $b['start'] ) {
				return $a['start'] <=> $b['start'];
			}
			return $b['end'] <=> $a['end'];
		} );

		return $tables;
	}

	private function tableHasCaption( array $table ): bool {
		$lines = explode( "|", $table['text'] );

		if ( !is_array( $lines ) || empty( $lines ) ) {
			return false;
		}

		foreach ( $lines as $line ) {
			$trimmedLine = ltrim( $line );
			if ( strpos( $trimmedLine, '+' ) === 0 && $this->isTableCaptionNotEmpty( $trimmedLine ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $line
	 * @return bool
	 */
	private function isTableCaptionNotEmpty( string $line ): bool {
		$caption = trim( substr( $line, 2 ) );

		if ( $caption === '' ) {
			return false;
		}

		$attributeSeparatorPosition = strpos( $caption, '|' );
		if ( $attributeSeparatorPosition !== false ) {
			$caption = trim( substr( $caption, $attributeSeparatorPosition + 1 ) );
		}

		return $caption !== '';
	}

	private function addTableCaption(
		string &$wikitext, string $label, array $table, int $num
	): void {
		$lines = preg_split( '#\r?\n#', $table['text'] );
		if ( !is_array( $lines ) ) {
			return;
		}

		if ( count( $lines ) === 1 ) {
			$line = $lines[0];
			$lines[0] = preg_replace( '#\{\|(.*?)[\!|\|]#', "{|$1|+ $label $num |", $line );
		} else {
			array_splice( $lines, 1, 0, [ "|+ $label $num" ] );
		}

		$wikitext = str_replace( $table['text'], implode( "\n", $lines ), $wikitext );
	}
}
