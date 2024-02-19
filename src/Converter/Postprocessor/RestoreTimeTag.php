<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreTimeTag implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$newWikiText = preg_replace(
			'#<span class="PRESERVEDATETIME">(.*?)</span>#si',
			'<datetime>$1</datetime>',
			$wikiText
		);

		return $newWikiText;
	}
}
