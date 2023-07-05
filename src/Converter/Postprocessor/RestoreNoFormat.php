<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreNoFormat implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$newWikiText = preg_replace(
			'#<pre class="PRESERVENOFORMAT">(.*?)</pre>#si',
			'<syntaxhighlight>$1</syntaxhighlight>',
			$wikiText
		);

		return $newWikiText;
	}
}
