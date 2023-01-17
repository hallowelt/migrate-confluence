<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class NestedHeadings implements IPostprocessor {

	private $regEx = '#(\*{1,6})\s?([=]{1,6})([^=]*)\s?([=]{1,6})#';

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$lines = explode( "\n", $wikiText );

		for ( $index = 0; $index < count( $lines ); $index++ ) {
			if ( strpos( $lines[$index], '*', 0 ) === 0 ) {
				if ( strpos( $lines[$index + 1], '*', 0 ) === 0 ) {
					$nextIndex = $this->processList( $lines, $index );
					$index = $nextIndex;
				} else {
					$this->processHeading( $lines, $index );
				}
			}
		}

		$wikiText = implode( "\n", $lines );

		return $wikiText;
	}

	/**
	 * @param array &$lines
	 * @param int $index
	 */
	private function processHeading( &$lines, $index ) {
		$matches = [];

		$line = $lines[$index];
		preg_match( $this->regEx, $line, $matches );

		if ( count( $matches ) > 0 ) {
			$orig = $matches[0];
			$headingLevel = $matches[2];
			$text = $matches[3];

			$lines[$index] = $this->getHeadingReplacement( $headingLevel, $text );
		}
	}

	/**
	 * @param array &$lines
	 * @param int $index
	 * @return int
	 */
	private function processList( &$lines, $index ): int {
		$matches = [];

		$line = $lines[$index];
		preg_match( $this->regEx, $line, $matches );
		while ( count( $matches ) > 0 ) {
			$orig = $matches[0];
			$listLevel = $matches[1];
			$text = $matches[3];

			$lines[$index] = $this->getListReplacement( $listLevel, $text );

			$index++;
			if ( $index >= count( $lines ) ) {
				$matches = 0;
			}

			$line = $lines[$index];
			preg_match( $this->regEx, $line, $matches );
		}

		return $index;
	}

	/**
	 * @param string $markup
	 * @param string $text
	 * @return string
	 */
	private function getListReplacement( $markup, $text ): string {
		return $markup . $text;
	}

	/**
	 * @param string $markup
	 * @param string $text
	 * @return string
	 */
	private function getHeadingReplacement( $markup, $text ): string {
		return $markup . $text . $markup;
	}
}
