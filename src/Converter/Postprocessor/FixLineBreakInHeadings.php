<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixLineBreakInHeadings implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		for ( $heading = 1; $heading <= 6; $heading++ ) {
			$regex = $this->buildRegExForHeadingLevel( $heading );
			$wikiText = preg_replace_callback(
				$regex,
				static function ( $matches ) {
					$wikiTextHeading = $matches[0];
					$newWikiTextHeading = str_replace( [ "<br />", "\n" ], ' ', $wikiTextHeading );
					return $newWikiTextHeading;
				},
				$wikiText
			);
		}
		return $wikiText;
	}

	/**
	 * @param int $level
	 *
	 * @return string
	 */
	private function buildRegExForHeadingLevel( int $level ): string {
		$tag = str_repeat( '=', $level );
		return "#^$tag.*?(<br \/>\n*?).*?$tag$#im";
	}
}
