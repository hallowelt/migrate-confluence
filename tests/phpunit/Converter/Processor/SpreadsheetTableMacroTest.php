<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CsvTableMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\JsonFromTableMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class SpreadsheetTableMacroTest extends ProcessorTestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\SpreadsheetTableMacroBase::process
	 * @dataProvider provideCases
	 * @param string $processorClass
	 * @param string $macroName
	 * @param string $parameters
	 * @param string $expected
	 * @param bool $expectMacroKept
	 * @return void
	 */
	public function testProcess(
		string $processorClass, string $macroName, string $parameters, string $expected, bool $expectMacroKept
	) {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace"><p>'
			. "<ac:structured-macro ac:name=\"$macroName\">$parameters</ac:structured-macro>"
			. '</p></xml>'
		);

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );
		$processor = new $processorClass( $dataLookup, 23, 'SomePage', new MigrationConfig( [] ) );
		$processor->process( $dom );
		$actual = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( $expected, $actual );
		$this->assertSame( $expectMacroKept, str_contains( $actual, 'ac:structured-macro' ) );
	}

	/**
	 * @return array
	 */
	public static function provideCases(): array {
		$attachment = static fn ( string $file ) => "<ac:parameter ac:name=\"attachment\">$file</ac:parameter>"
			. '<ac:parameter ac:name="source">attachment</ac:parameter>';

		return [
			'csv-table' => [
				CsvTableMacro::class, 'csv-table', $attachment( 'Dummy_3.xls' ),
				'{{#spreadsheettable:DEVOPS:SomePage-Dummy_3.xls}}', false
			],
			'json-from-table' => [
				JsonFromTableMacro::class, 'json-from-table',
				$attachment( 'Dummy_3.xls' ) . '<ac:parameter ac:name="format">markdown</ac:parameter>',
				'{{#spreadsheettable:DEVOPS:SomePage-Dummy_3.xls}}', false
			],
			'attachment not migrated' => [
				CsvTableMacro::class, 'csv-table', $attachment( 'Unknown.csv' ),
				'[[Category:Broken_macro/csv-table]]', true
			],
			'source other than attachment' => [
				JsonFromTableMacro::class, 'json-from-table',
				'<ac:parameter ac:name="source">url</ac:parameter>',
				'[[Category:Broken_macro/json-from-table]]', true
			],
		];
	}
}
