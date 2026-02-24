<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorMacro;
use PHPUnit\Framework\TestCase;

class AnchorMacroTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AnchorMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/anchor-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new AnchorMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/anchor-macro-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
