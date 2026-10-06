<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="sm" ac:schema-version="1" ac:macro-id="12345">
 *   <ac:rich-text-body>Product Name</ac:rich-text-body>
 * </ac:structured-macro>
 */
class SmMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'sm';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'SM';
	}

	/**
	 * @return bool
	 */
	protected function addLinebreakInsideTemplate(): bool {
		return false;
	}

	/**
	 * @return bool
	 */
	protected function addLinebreakAfterTemplate(): bool {
		return false;
	}
}
