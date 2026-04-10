<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreExcerptBlock implements IPostprocessor {

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
				return "<excerpt-block name=\"$name\" hidden=\"$hidden\">$content</excerpt-block>";
			},
			$wikiText
		);
	}
}
