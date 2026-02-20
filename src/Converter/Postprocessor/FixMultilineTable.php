<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixMultilineTable implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$wikiText = preg_replace_callback(
			'/\{\|(.*?)\|\}/s',
			static function ( $match ) {
				$lines = explode( "\n", $match[0] );

				$detectedLines = [];
				$problematicLines = [];
				// Starting with index = 1 and ending with index < count()
				// will cut off table start ({|) and table end (|}).
				for ( $index = 1; $index < count( $lines ); $index++ ) {
					if ( in_array( $index - 1, $detectedLines ) ) {
						// skip multiple lines, Only the first has to be fixed.
						continue;
					}

					$line = $lines[$index];
					
					if ( strpos( $line, '|-' ) === 0 ) {
						continue;
					} else if ( strpos( $line, '|' ) === 0 ) {
						continue;
					} else if ( strpos( $line, '!' ) === 0 ) {
						continue;
					}

					$detectedLines[] = $index;
					$problematicLines[] = $index -1 ;
				}

				$fixedLines = [];
				foreach ( $problematicLines as $problematicLine ) {
					$line = $lines[$problematicLine];
					$newLine = "|\n";

					if ( strpos( $line, '| ' ) === 0 ) {
						$newLine .= substr( $line, 2 );
					} elseif ( strpos( $line, '|' ) === 0 ) {
						$newLine .= substr( $line, 1 );
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
