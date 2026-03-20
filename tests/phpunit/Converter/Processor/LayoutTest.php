<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Layout;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Layout::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/layout-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Layout();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/layout-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
