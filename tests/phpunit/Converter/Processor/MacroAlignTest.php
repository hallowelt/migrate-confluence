<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\MacroAlign;
use PHPUnit\Framework\TestCase;

class MacroAlignTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\MacroAlignTest::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/macroalign-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$processor = new MacroAlign();
			$processor->process( $dom );

			$actualOutput = $dom->saveXML( $dom->documentElement );

			$input = file_get_contents( "$dir/macroalign-output.xml" );
			$expectedDom = new DOMDocument();
			$expectedDom->loadXML( $input );
			$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
