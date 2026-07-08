<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CodeMacro;

class CodeMacroTest extends ProcessorTestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CodeMacro::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$codeMacroProcessor = new CodeMacro();
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
		$dir = dirname(  __DIR__, 2 );
		return [
			'standard' => [
				"$dir/data/code-standard-input.xml",
				"$dir/data/code-standard-output.xml",
			],
			'no-body' => [
				"$dir/data/code-nobody-input.xml",
				"$dir/data/code-nobody-output.xml",
			]
		];
	}
}
