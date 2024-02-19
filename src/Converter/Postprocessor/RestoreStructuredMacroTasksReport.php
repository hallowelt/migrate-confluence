<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreStructuredMacroTasksReport implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$newWikiText = preg_replace(
			'#<div class="PRESERVETASKSREPORT"(.*?)>.*?</div>#si',
			'<taskreport$1/>',
			$wikiText
		);
		return $newWikiText;
	}
}
