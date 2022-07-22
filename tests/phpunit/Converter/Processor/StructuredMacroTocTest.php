<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroToc;
use PHPUnit\Framework\TestCase;

class StructuredMacroTocTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroTocTest::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/structuredmacrotoc-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$processor = new StructuredMacroToc();
			$processor->process( $dom );

			$actualOutput = $dom->saveXML( $dom->documentElement );

			$input = file_get_contents( "$dir/structuredmacrotoc-output.xml" );
			$expectedDom = new DOMDocument();
			$expectedDom->loadXML( $input );
			$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
