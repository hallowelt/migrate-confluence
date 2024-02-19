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
			$newWikiText = preg_replace_callback(
				$regex,
				static function ( $matches ) {
					$wikiTextHeading = $matches[0];
					$newWikiTextHeading = str_replace( [ "<br />", "\n" ], ' ', $wikiTextHeading );
					return $newWikiTextHeading;
				},
				$wikiText
			);
		}
		return $newWikiText;
	}

	/**
	 * @param int $level
	 * @return string
	 */
	private function buildRegExForHeadingLevel( $level ): string {
		$tag = '';
		for ( $heading = 1; $heading <= $level; $heading++ ) {
			$tag = '.=';
		}
		return "#^$tag.*?(<br \/>\n*?).*?$tag$#im";
	}
}
