<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CopyrightMacro;

class CopyrightMacroTest extends ProcessorTestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CopyrightMacro::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$processor = new CopyrightMacro();
		$processor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( $expectedOutputFile );

		$this->assertEquals( $expectedDom->saveXML(), $dom->saveXML() );
	}

	/**
	 * @return array
	 */
	public function provideTestProcessData() {
		$dir = dirname( __DIR__, 2 );
		return [
			'standard' => [
				"$dir/data/copyright-standard-input.xml",
				"$dir/data/copyright-standard-output.xml",
			],
			'with-attrs' => [
				"$dir/data/copyright-with-attrs-input.xml",
				"$dir/data/copyright-with-attrs-output.xml",
			],
			'no-body' => [
				"$dir/data/copyright-nobody-input.xml",
				"$dir/data/copyright-nobody-output.xml",
			],
		];
	}
}
