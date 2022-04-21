<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixImagesWithExternalUrl implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$wikiText = preg_replace_callback(
			"/\[\[File:(http[s]?:\/\/.*)]]/",
			function ( $matches ) {
				$attributes = [];

				if ( strpos( $matches[1], '|' ) ) {
					$params = explode( '|', $matches[1] );
					$replacement = $params[0];

					// handle attibute for height
					for ( $index = 1; $index < count( $params ); $index++ ) {
						if ( strpos( $params[$index], 'x', 0 ) !== false
							&& strpos( $params[$index], 'px', strlen( $params[$index] ) - 2 ) !== false ) {

							$height = [];
							preg_match( "/([0-9]+)/", $params[$index], $height );
							if ( count( $height ) > 0 ) {
								$attributes[] = 'height="' . $height[1] . '"';
							}
						}
					}
				} else {
					$replacement = $matches[1];
				}

				$attribs = implode( ' ', $attributes );
				if ( parse_url( $attribs ) ) {
					$attr = $attribs;
				}
				if ( parse_url( $matches[1] ) ) {
					return '<img src="' . $replacement . '" ' . $attr . ' />';
				}
				return $matches[0];
			},
			$wikiText
		);

		return $wikiText;
	}
}
