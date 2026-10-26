<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Wikitext tables cannot express column groups, so Pandoc drops the element and its
 * parameters (span, width). A `<col>` child must not be emitted: Pandoc then loses table cells.
 */
class ColgroupMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'colgroup';

	public const TAG = 'colgroup';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;
}
