<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreExcerptMacro implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		return preg_replace_callback(
			'/#####EXCERPTBLOCKOPEN\|(.*?)\|(.*?)#####(.*?)#####EXCERPTBLOCKCLOSE#####/si',
			static function ( $matches ) {
				$attributes = [
					'name'   => $matches[1] ?? '',
					'hidden' => $matches[2] ?? '',
				];

				$attrString = '';
				foreach ( $attributes as $attr => $value ) {
					if ( $value !== '' ) {
						$attrString .= sprintf( ' %s="%s"', $attr, $value );
					}
				}

				$content = trim( $matches[3] ?? '' );

				return "<excerpt-block$attrString>$content</excerpt-block>";
			},
			$wikiText
		);
	}

}
