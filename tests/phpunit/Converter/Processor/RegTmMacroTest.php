<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\RegTmMacro;

class RegTmMacroTest extends ProcessorTestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RegTmMacro::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$processor = new RegTmMacro();
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
				"$dir/data/reg-tm-standard-input.xml",
				"$dir/data/reg-tm-standard-output.xml",
			],
			'with-attrs' => [
				"$dir/data/reg-tm-with-attrs-input.xml",
				"$dir/data/reg-tm-with-attrs-output.xml",
			],
			'no-body' => [
				"$dir/data/reg-tm-nobody-input.xml",
				"$dir/data/reg-tm-nobody-output.xml",
			],
		];
	}
}
