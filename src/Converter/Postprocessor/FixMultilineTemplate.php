<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixMultilineTemplate implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$wikiText = preg_replace_callback(
			'/\{\{(.*?)\}\}/s',
			function( $match ) {
				$lines = explode( "###BREAK###", $match[0] );
				for( $index = 0; $index < count( $lines ); $index++ ) {
					$line = $lines[$index];
					if ( strpos( $line, ' ' ) === 0 ) {
						$lines[$index] = substr( $line, 1 );
					}
				}
				return implode( "###BREAK###", $lines );
			},
			$wikiText
		);

		return $wikiText;
	}
}
