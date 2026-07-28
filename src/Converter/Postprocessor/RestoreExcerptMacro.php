<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreExcerptMacro implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		return preg_replace_callback(
			'/#####EXCERPT(BLOCK|INLINE)OPEN\|(.*?)\|(.*?)#####(.*?)#####EXCERPT\1CLOSE#####/si',
			static function ( $matches ) {
				$tag = 'excerpt-' . strtolower( $matches[1] );
				$attributes = [
					'name'   => $matches[2] ?? '',
					'hidden' => $matches[3] ?? '',
				];
				$attrString = '';
				foreach ( $attributes as $attr => $value ) {
					if ( $value !== '' ) {
						$attrString .= sprintf( ' %s="%s"', $attr, $value );
					}
				}
				$content = trim( $matches[4] ?? '' );
				return "<$tag$attrString>$content</$tag>";
			},
			$wikiText
		);
	}
}
