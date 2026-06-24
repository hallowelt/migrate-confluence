<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RemoveMultipleLinebreaks implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$lineSeparator = str_contains( $wikiText, "\r\n" ) ? "\r\n" : "\n";
		$normalizedText = str_replace( [ "\r\n", "\r" ], "\n", $wikiText );
		$lines = explode( "\n", $normalizedText );

		$processedLines = [];
		$maxConsecutiveBlocks = 3;
		$consecutiveBlocks = 0;
		$totalLines = count( $lines );

		for ( $index = 0; $index < $totalLines; $index++ ) {
			$currentLine = $lines[$index];

			if ( preg_match( '/^\s*&lt;\s*br\s*\/?\s*&gt;\s*$/i', $currentLine ) ) {
				$emptyLinesAfterTag = 0;
				$probeIndex = $index + 1;

				while (
					$probeIndex < $totalLines &&
					trim( $lines[$probeIndex] ) === '' &&
					$emptyLinesAfterTag < 2
				) {
					$emptyLinesAfterTag++;
					$probeIndex++;
				}

				if ( $emptyLinesAfterTag > 0 ) {
					$consecutiveBlocks++;

					if ( $consecutiveBlocks <= $maxConsecutiveBlocks ) {
						$processedLines[] = $currentLine;
						for ( $emptyIndex = 0; $emptyIndex < $emptyLinesAfterTag; $emptyIndex++ ) {
							$processedLines[] = '';
						}
					}

					$index = $probeIndex - 1;
					continue;
				}
			}

			if ( trim( $currentLine ) !== '' ) {
				$consecutiveBlocks = 0;
			}

			$processedLines[] = $currentLine;
		}

		$processedText = implode( "\n", $processedLines );
		if ( $lineSeparator === "\r\n" ) {
			return str_replace( "\n", "\r\n", $processedText );
		}

		return $processedText;
	}
}
