<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AlignMacro;

class AlignMacroTest extends ProcessorTestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AlignMacro::process
		 * @return void
		 */
	public function testProcess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/align-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new AlignMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expected = file_get_contents( "$dir/align-macro-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $expected );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
