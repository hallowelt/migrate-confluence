<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\MarkdownMacro;

class MarkdownMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\MarkdownMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/markdown-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new MarkdownMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/markdown-macro-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\MarkdownMacro::process
	 * @return void
	 */
	public function testProcessBrokenMacro() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/markdown-macro-broken-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new MarkdownMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/markdown-macro-broken-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
