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
				$showpanel = "";
				if ( !empty( $matches[1] ) ) {
					$showpanel = " showpanel=\"$matches[1]\"";
				}

				$page = "";
				if ( !empty( $matches[2] ) ) {
					$page = " page=\"$matches[2]\"";
				}

				$excerpt = "";
				if ( !empty( $matches[3] ) ) {
					$excerpt = " excerpt=\"$matches[3]\"";
				}

				return sprintf( '<excerpt-include%s%s%s/>', $showpanel, $excerpt, $page );
			},
			$wikiText
		);
	}

}
