<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class PanelMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'panel';

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
