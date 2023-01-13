<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class NestedHeadings implements IPostprocessor {

	private $regEx = '#(\*{1,6})\s?([=]{1,6})([^=]*)\s?([=]{1,6})#';

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$matches = [];
		preg_match_all( $this->regEx, $wikiText, $matches );

		for ( $index = 0; $index < count( $matches[0] ); $index++ ) {
			$orig = $matches[0][$index];
			# $ListLevel = $matches[1][$index];
			$headingLevel = $matches[2][$index];
			$text = $matches[3][$index];

			$replacement = $this->getReplacement( $headingLevel, $text );

			$wikiText = str_replace( $orig, $replacement, $wikiText );
		}

		return $wikiText;
	}

	/**
	 * @param string $markup
	 * @param string $text
	 * @return string
	 */
	private function getReplacement( $markup, $text ): string {
		return $markup . $text . $markup;
	}
}
