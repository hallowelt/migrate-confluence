<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class ColumnMacro extends ConvertMacroToTemplateBase {

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
	protected function getWikiTextTemplateName(): string {
		return 'Column';
	}
}
