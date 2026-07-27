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
				$name = "";
				if ( !empty( $matches[1] ) ) {
					$name = " name=\"$matches[1]\"";
				}

				$hidden = "";
				if ( !empty( $matches[2] ) ) {
					$hidden = " hidden=\"$matches[2]\"";
				}

				$content = trim( $matches[3] ) ?? "";

				return sprintf( '<excerpt-block%s%s>%s</excerpt-block>', $name, $hidden, $content );
			},
			$wikiText
		);
	}

}
