<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;
use PHPUnit\Framework\TestCase;

class UnhandledMacroConverterTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dir = dirname( __DIR__ ) . '/data';
		$input = file_get_contents( "$dir/unhandled-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new UnhandledMacroConverter();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/unhandled-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
