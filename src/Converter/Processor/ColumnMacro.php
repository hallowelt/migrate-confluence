<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class ColumnMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'column';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 *
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'ColumnStart';
	}

	/**
	 *
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'ColumnEnd';
	}
}
