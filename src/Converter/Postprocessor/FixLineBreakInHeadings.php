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
					$openTag = $matches[1];
					$content = $matches[2];
					$closeTag = $matches[3];
					// Strip <br /> variants and collapse newlines to spaces.
					$content = str_replace( [ '<br />', '<br/>', "\r\n", "\n", "\r" ], ' ', $content );
					// Collapse multiple spaces and trim.
					$content = trim( preg_replace( '/ {2,}/', ' ', $content ) );
					return "$openTag $content $closeTag";
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
		// Capture (1) opening tag, (2) content containing at least one <br />,
		// (3) closing tag. The 's' flag lets '.' match newlines so that a <br />
		// followed by a real newline (multiline heading) is also matched.
		// The 'm' flag anchors '^' to the start of any line.
		// '(?!=)' after the opening tag prevents '===' from matching '===='.
		return "#^($tag(?!=))(.*?<br\s*/?>.*?)($tag)\s*$#ms";
	}
}
