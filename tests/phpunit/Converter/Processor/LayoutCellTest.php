<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\LayoutCell;
use PHPUnit\Framework\TestCase;

class LayoutCellTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\LayoutCell::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/layout-cell-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new LayoutCell();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/layout-cell-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
