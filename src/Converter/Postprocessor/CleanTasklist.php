<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreCode implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$newWikiText = preg_replace(
			'#<pre class="PRESERVESYNTAXHIGHLIGHT"(.*?)>(.*?)</pre>#si',
			'<syntaxhighlight$1>$2</syntaxhighlight>',
			$wikiText
		);

		return $newWikiText;
	}
}
