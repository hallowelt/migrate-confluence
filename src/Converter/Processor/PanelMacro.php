<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class PanelMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'panel';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'ParserFunctions' ];

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'PanelStart';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'PanelEnd';
	}
}
