<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor\BluespiceGalaxy;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreStatusMacro implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		return preg_replace_callback(
			'/#####STATUSOPEN(.*?)#####(.*?)#####STATUSCLOSE#####/si',
			static function ( $matches ) {
				$tag = 'status';
				$attributes = html_entity_decode( $matches[1] );
				$content = html_entity_decode( $matches[2] );
				return "<$tag$attributes>$content</$tag>";
			},
			$wikiText
		);
	}
}
