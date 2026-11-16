<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Version comments are not migrated, so only a link to the page history can be offered.
 * The `limit` parameter is passed on to the template, but has no effect.
 */
class ChangeHistoryMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'change-history';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ChangeHistory';
	}
}
