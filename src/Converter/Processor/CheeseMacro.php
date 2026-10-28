<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="cheese"><ac:rich-text-body>I like cheese!</ac:rich-text-body></ac:structured-macro>
 */
class CheeseMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'cheese';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Cheese';
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
