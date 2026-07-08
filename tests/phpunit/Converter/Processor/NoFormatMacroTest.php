<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\NoFormatMacro;

class NoFormatMacroTest extends ProcessorTestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\NoFormatMacroNoFormat::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$codeMacroProcessor = new NoFormatMacro();
		$codeMacroProcessor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( $expectedOutputFile );

		$this->assertEquals( $expectedDom->saveXML(), $dom->saveXML() );
	}

	/**
	 *
	 * @return array
	 */
	public function provideTestProcessData() {
		$dir = dirname( __DIR__, 2 );
		return [
			'standard' => [
				"$dir/data/noformat-standard-input.xml",
				"$dir/data/noformat-standard-output.xml",
			],
			'no-body' => [
				"$dir/data/noformat-nobody-input.xml",
				"$dir/data/noformat-nobody-output.xml",
			]
		];
	}
}
