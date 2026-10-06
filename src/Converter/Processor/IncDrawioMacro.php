<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Handles Confluence's "inc-drawio" macro, which embeds a DrawIO diagram that is attached
 * to a *different* page (identified by the "pageId" parameter) instead of the current page.
 */
class IncDrawioMacro extends DrawioMacro {

	public const MACRO_NAME = 'inc-drawio';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'DrawioEditor', 'ParserFunctions' ];
}
