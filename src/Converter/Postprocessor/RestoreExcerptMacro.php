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
				$name = $matches[1];
				$hidden = $matches[2];
				$content = $matches[3];

				$wikiText = "<!-- start excerpt macro -->";
				$wikiText .= "<div data-macro=\"excerpt\" name=\"$name\" hidden=\"$hidden\">$content</div>";
				$wikiText .= "<!-- end excerpt macro -->";

				return $wikiText;
			},
			$wikiText
		);
	}
}
