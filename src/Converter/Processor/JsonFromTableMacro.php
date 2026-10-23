<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="json-from-table">
 *	<ac:parameter ac:name="attachment">Data.json</ac:parameter>
 *	<ac:parameter ac:name="source">attachment</ac:parameter>
 *	<ac:parameter ac:name="format">markdown</ac:parameter>
 *	<ac:parameter ac:name="expand">true</ac:parameter>
 * </ac:structured-macro>
 */
class JsonFromTableMacro extends SpreadsheetTableMacroBase {

	public const MACRO_NAME = 'json-from-table';
}
