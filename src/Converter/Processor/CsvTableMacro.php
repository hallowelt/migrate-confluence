<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="csv-table">
 *	<ac:parameter ac:name="attachment">Data.csv</ac:parameter>
 *	<ac:parameter ac:name="source">attachment</ac:parameter>
 * </ac:structured-macro>
 */
class CsvTableMacro extends SpreadsheetTableMacroBase {

	public const MACRO_NAME = 'csv-table';
}
