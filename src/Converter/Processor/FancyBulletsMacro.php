<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Keeps the list in the body; the bullet styling is not migrated.
 * The parameters stay as attributes of the wrapping `div`, so they are not lost.
 */
class FancyBulletsMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'fancy-bullets';

	public const TAG = 'div';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;
}
