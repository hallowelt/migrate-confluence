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
				$macroId = $matches[1];
				$hidden = $matches[2];
				$content = $matches[3];
				return "<div class=\"excerpt-block\" name=\"$macroId\" hidden=\"$hidden\">$content</div>";
			},
			$wikiText
		);
	}
}
