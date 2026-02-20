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
			static function ( $match ) {
				$lines = explode( "###BREAK###", $match[0] );

				// Remove whitespaces an beginning of lines
				for ( $index = 0; $index < count( $lines ); $index++ ) {
					$line = $lines[$index];
					if ( strpos( $line, ' ' ) === 0 ) {
						$lines[$index] = substr( $line, 1 );
					}
				}

				// Start a new line after param "body"
				for ( $index = 0; $index < count( $lines ); $index++ ) {
					$line = $lines[$index];
					if ( strpos( $line, '|body=' ) !== 0 ) {
						continue;
					}
					if ( strlen( '|body=' ) === strlen( $line ) ) {
						continue;
					}

					$newLine = '|body=###BREAK###';
					$newLine .= substr( $line, strlen( '|body=' ) );

					$lines[$index] = $newLine;
				}
				return implode( "###BREAK###", $lines );
			},
			$wikiText
		);

		return $wikiText;
	}
}
