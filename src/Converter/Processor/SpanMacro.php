<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class SpanMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'span';

	public const TAG = 'span';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;
}
