<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class UlMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'ul';

	public const TAG = 'ul';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
