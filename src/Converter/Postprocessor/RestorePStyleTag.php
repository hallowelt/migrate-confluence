<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestorePStyleTag implements IPostprocessor {

	/**
	 * @inheritDoc
	 *
	 * @return null|string
	 */
	public function postprocess( string $wikiText ): string|null {
		$newWikiText = preg_replace_callback(
			'/\\\\?#####PRESERVEPSTYLEOPEN (.*?)#####(.*?)#####PRESERVEPSTYLECLOSE#####/si',
			static function ( $matches ) {
				$attributes = str_replace( "&quot;", "\"", $matches[1] );
				$text = $matches[2];
				return "<p $attributes>$text</p>";
			},
			$wikiText
		);

		return $newWikiText;
	}

}
