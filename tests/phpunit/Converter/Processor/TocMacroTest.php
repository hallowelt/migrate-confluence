<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TocMacro;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;
use PHPUnit\Framework\TestCase;

class TocMacroTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TocMacro::process
		 * @return void
		 */
	public function testPreprocess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/toc-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$macroUsage = new TocMacroUsage();
		$this->assertFalse( $macroUsage->getStatus() );

		$processor = new TocMacro( $macroUsage );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/toc-macro-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
		$this->assertTrue( $macroUsage->getStatus() );
	}
}
