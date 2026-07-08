<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\LayoutSection;

class LayoutSectionTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\LayoutSection::preprocess
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname(  __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/layout-section-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new LayoutSection();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/layout-section-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
