<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroRecentlyUpdated;
use PHPUnit\Framework\TestCase;

class StructuredMacroRecentlyUpdatedTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroRecentlyUpdated::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/structuredmacrorecentlyupdated-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$processor = new StructuredMacroRecentlyUpdated( 'ABC:SomePage_1' );
			$processor->process( $dom );

			$actualOutput = $dom->saveXML( $dom->documentElement );

			$input = file_get_contents( "$dir/structuredmacrorecentlyupdated-output.xml" );

			$expectedDom = new DOMDocument();
			$expectedDom->loadXML( $input );

			$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
