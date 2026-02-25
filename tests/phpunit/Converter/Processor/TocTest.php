<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Toc;
use PHPUnit\Framework\TestCase;

class TocTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TocTest::preprocess
		 * @return void
		 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/toc-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Toc();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/toc-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
