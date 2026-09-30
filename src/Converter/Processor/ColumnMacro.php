<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class ColumnMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'column';

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
