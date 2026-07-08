<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Layout;

class LayoutTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Layout::preprocess
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname(  __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/layout-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Layout();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		# $actualOutput = preg_replace( '/\s+xmlns(?::[A-Za-z_][A-Za-z0-9_.-]*)?="[^"]*"/', '', $actualOutput );

		$input = file_get_contents( "$dir/layout-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );
		# $expectedOutput = preg_replace( '/\s+xmlns(?::[A-Za-z_][A-Za-z0-9_.-]*)?="[^"]*"/', '', $expectedOutput );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
