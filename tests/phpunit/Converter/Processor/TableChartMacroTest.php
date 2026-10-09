<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TableChartMacro;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class TableChartMacroTest extends ProcessorTestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\TableChartMacro::process
	 * @dataProvider provideCases
	 * @param string $case
	 * @return void
	 */
	public function testProcess( string $case ) {
		$dir = dirname( __DIR__, 2 ) . '/data/TableChart';
		$dom = new DOMDocument();
		$dom->loadXML( file_get_contents( "$dir/table-chart-macro-$case-input.xml" ) );

		$placeholderManager = new PlaceholderManager();
		$processor = new TableChartMacro( $placeholderManager );
		$processor->process( $dom );
		$actual = $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/table-chart-macro-$case-output.xml" ) );
		$expected = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expected, $actual, "Failed converting table-chart case $case" );
	}

	/**
	 * @return array
	 */
	public static function provideCases(): array {
		return [
			'line' => [ 'line' ],
			'column' => [ 'column' ],
			'pie' => [ 'pie' ],
			'unsupported type' => [ 'radar' ],
			'csv-table data source' => [ 'csv' ],
			'negative value' => [ 'negative' ],
		];
	}
}
