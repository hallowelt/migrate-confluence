<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveCode;
use PHPUnit\Framework\TestCase;

class PreserveCodeTest extends TestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PreserveCode::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$codeMacroProcessor = new PreserveCode();
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
		return [
			'standard' => [
				__DIR__ . '../data/code-standard-input.xml',
				__DIR__ . '../data/code-standard-output.xml',
			],
			'no-body' => [
				__DIR__ . '../data/code-nobody-input.xml',
				__DIR__ . '../data/code-nobody-output.xml',
			]
		];
	}
}
