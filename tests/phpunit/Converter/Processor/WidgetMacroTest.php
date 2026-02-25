<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro;
use PHPUnit\Framework\TestCase;

class WidgetMacroTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro::preprocess
		 * @return void
		 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/widget-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new WidgetMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/widget-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
