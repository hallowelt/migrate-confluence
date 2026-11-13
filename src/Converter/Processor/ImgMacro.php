<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Becomes a plain `<img>`, which `ImgHtml` converts afterwards; it must be registered before `ImgHtml`.
 */
class ImgMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'img';

	public const TAG = 'img';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;
}
