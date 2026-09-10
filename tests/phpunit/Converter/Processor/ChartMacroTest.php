<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\ChartMacro;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ChartMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class ChartMacroTest extends ProcessorTestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ChartMacro::process
		 * @return void
		 */
	public function testProcess() {
		$this->doTest( 'pie' );
		$this->doTest( 'bar' );
		$this->doTest( 'line' );
		$this->doTest( 'area' );
		$this->doTest( 'scatter' );
		$this->doTest( 'gantt' );
	}

	/**
	 * @param string $type
	 * @return void
	 */
	private function doTest( string $type ) {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( $dir . "/chart-macro-$type-input.xml" );
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$placeholderManager = new PlaceholderManager();

		$processor = new ChartMacro( $placeholderManager );
		$processor->process( $dom );
		$actual = $dom->saveXML( $dom->documentElement );

		$output = file_get_contents( $dir . "/chart-macro-$type-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $output );
		$expected = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expected, $actual, "Failed creating chart of type $type" );
	}
}
