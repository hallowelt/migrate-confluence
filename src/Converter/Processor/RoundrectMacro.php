<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="roundrect">
 *   <ac:parameter ac:name="title">Title</ac:parameter>
 *   <ac:rich-text-body>...</ac:rich-text-body>
 * </ac:structured-macro>
 */
class RoundrectMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'roundrect';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'RoundRect';
	}
}
