<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class TasksReportMacro implements IPostprocessor {

	/**
	 * @inheritDoc
	 *
	 * @return null|string
	 */
	public function postprocess( string $wikiText ): string|null {
		$newWikiText = preg_replace(
			'#<div class="PRESERVETASKSREPORT"(.*?)>.*?</div>#si',
			'<taskreport$1/>',
			$wikiText
		);
		return $newWikiText;
	}

}
