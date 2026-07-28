<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreExcerptIncludeMacro implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		return preg_replace_callback(
			'/#####EXCERPTINCLUDE\|(.*?)\|(.*?)\|(.*?)#####/',
			static function ( $matches ) {
				$attributes = [
					'showpanel' => $matches[1] ?? '',
					'excerpt'   => $matches[3] ?? '',
					'page'      => $matches[2] ?? '',
				];

				$attrString = '';
				foreach ( $attributes as $name => $value ) {
					if ( $value !== '' ) {
						$attrString .= sprintf( ' %s="%s"', $name, $value );
					}
				}

				return "<excerpt-include$attrString/>";
			},
			$wikiText
		);
	}

}
