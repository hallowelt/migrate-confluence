<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class TdMacro extends LegacyTableMacroBase {

	public const MACRO_NAME = 'td';

	public const TAG = 'td';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;
}
