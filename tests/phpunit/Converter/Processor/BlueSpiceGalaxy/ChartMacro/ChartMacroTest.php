<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy\ChartMacro;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ChartMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;

class ChartMacroTest extends ProcessorTestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ChartMacro::process
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
		$input = file_get_contents( __DIR__ . "/$type-input.xml" );
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ChartMacro();
		$processor->process( $dom );
		$actual = $dom->saveXML( $dom->documentElement );

		$output = file_get_contents( __DIR__ . "/$type-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $output );
		$expected = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expected, $actual, "Failed creating chart of type $type" );
	}
}
