<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="lozenge">
 *   <ac:parameter ac:name="title">Done</ac:parameter>
 *   <ac:parameter ac:name="color">green</ac:parameter>
 * </ac:structured-macro>
 */
class LozengeMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'lozenge';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Lozenge';
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
