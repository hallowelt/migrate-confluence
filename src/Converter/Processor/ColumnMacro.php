<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class ColumnMacro extends ConvertMacroToTemplateWithBodyBase {

	/**
	 *
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'column';
	}

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
