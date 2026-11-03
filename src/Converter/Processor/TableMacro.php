<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class TableMacro extends LegacyTableMacroBase {

	public const MACRO_NAME = 'table';

	public const TAG = 'table';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;
}
