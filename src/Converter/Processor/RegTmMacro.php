<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="reg-tm" ac:schema-version="1" ac:macro-id="12345">
 *   <ac:rich-text-body>Product Name</ac:rich-text-body>
 * </ac:structured-macro>
 */
class RegTmMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'reg-tm';

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'RegTM';
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
