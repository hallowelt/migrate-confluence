<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class TemplateContentPostProcessor implements IPostprocessor {

	/**
	 * @param string $wikiText
	 * @return string
	 */
	public function postprocess( string $wikiText ): string {
		return $wikiText . "\n<includeonly>{{#set: Created by macro=$1 }}</includeonly>";
	}

	/**
	 * @param string $pageTitle
	 * @return bool
	 */
	public function skipForPageTitle( string $pageTitle ): bool {
		if ( !str_starts_with( $pageTitle, 'Template:' ) ) {
			return true;
		}

		return false;
	}
}
