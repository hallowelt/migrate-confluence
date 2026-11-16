<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class LiMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'li';

	public const TAG = 'li';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
