<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

/**
 * Converts the Confluence table excerpt macro to a BlueSpice <excerpt-block> or <excerpt-inline> element.
 */
class TableExcerptMacro extends ExcerptMacro {

	public const MACRO_NAME = 'table-excerpt';
}
