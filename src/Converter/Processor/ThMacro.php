<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class ThMacro extends LegacyTableMacroBase {

	public const MACRO_NAME = 'th';

	public const TAG = 'th';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;
}
