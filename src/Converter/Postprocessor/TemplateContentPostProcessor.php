<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class TemplateContentPostProcessor implements IPostprocessor {

	/**
	 * @param string $currentWikiTitle
	 */
	public function __construct( private string $currentWikiTitle ) {
	}

	/**
	 * @param string $wikiText
	 * @return string
	 */
	public function postprocess( string $wikiText ): string {
		if ( !str_starts_with( $this->currentWikiTitle, 'Template:' ) ) {
			return $wikiText;
		}

		return $wikiText . "\n<includeonly>{{#set: Created by macro=$1 }}</includeonly>";
	}
}
