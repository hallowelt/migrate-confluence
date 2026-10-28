<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class TheadMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'thead';

	public const TAG = 'thead';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;
}
