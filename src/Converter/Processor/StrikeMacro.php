<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class StrikeMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'strike';

	public const TAG = 's';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
