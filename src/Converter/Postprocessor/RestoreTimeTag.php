<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreTimeTag implements IPostprocessor {

	/**
	 * @inheritDoc
	 *
	 * @return null|string
	 */
	public function postprocess( string $wikiText ): string|null {
		$newWikiText = preg_replace(
			'#<span class="PRESERVEDATETIME">(.*?)</span>#si',
			'<datetime>$1</datetime>',
			$wikiText
		);

		return $newWikiText;
	}

}
