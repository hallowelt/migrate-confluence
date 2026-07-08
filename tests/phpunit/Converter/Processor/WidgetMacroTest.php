<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro;

class WidgetMacroTest extends ProcessorTestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro::preprocess
		 * @return void
		 */
	public function testProcess() {
		$dir = dirname(  __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/widget-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new WidgetMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/widget-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
