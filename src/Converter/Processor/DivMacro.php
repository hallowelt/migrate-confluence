<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="div"><ac:parameter ac:name="class">x</ac:parameter>
 *   <ac:rich-text-body>...</ac:rich-text-body></ac:structured-macro>
 */
class DivMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'div';

	public const TAG = 'div';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
