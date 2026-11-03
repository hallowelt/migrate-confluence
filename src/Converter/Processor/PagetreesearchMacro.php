<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Whether the search form honors the page prefix depends on the search backend of the wiki.
 */
class PagetreesearchMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'pagetreesearch';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;

	public const REQUIRED_EXTENSIONS = [ 'Widgets' ];

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'PageTreeSearch';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWidgetNames(): array {
		return [ 'SearchForm' ];
	}
}
