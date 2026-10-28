<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class TbodyMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'tbody';

	public const TAG = 'tbody';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;
}
