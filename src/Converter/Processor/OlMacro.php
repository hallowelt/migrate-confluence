<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class OlMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'ol';

	public const TAG = 'ol';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
