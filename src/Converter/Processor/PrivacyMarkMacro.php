<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="privacy-mark">
 *   <ac:parameter ac:name="title">Contains personal data</ac:parameter>
 * </ac:structured-macro>
 */
class PrivacyMarkMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'privacy-mark';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'PrivacyMark';
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
