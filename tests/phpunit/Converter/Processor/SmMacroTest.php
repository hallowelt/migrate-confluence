<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\SmMacro;

class SmMacroTest extends ProcessorTestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\SmMacro::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$processor = new SmMacro();
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
				"$dir/data/sm-standard-input.xml",
				"$dir/data/sm-standard-output.xml",
			],
			'with-attrs' => [
				"$dir/data/sm-with-attrs-input.xml",
				"$dir/data/sm-with-attrs-output.xml",
			],
			'no-body' => [
				"$dir/data/sm-nobody-input.xml",
				"$dir/data/sm-nobody-output.xml",
			],
		];
	}
}
