<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Space restrictions, grouping and filters of the Confluence macro are not available in the wiki search.
 */
class SearchBoxMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'search-box';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;

	public const REQUIRED_EXTENSIONS = [ 'Widgets' ];

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'SearchBox';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWidgetNames(): array {
		return [ 'SearchForm' ];
	}
}
