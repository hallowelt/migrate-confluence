<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AlignMacro;
use PHPUnit\Framework\TestCase;

class AlignMacroTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AlignMacroTest::preprocess
		 * @return void
		 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/align-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new AlignMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/align-macro-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
