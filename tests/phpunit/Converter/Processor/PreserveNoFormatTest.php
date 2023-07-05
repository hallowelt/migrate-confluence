<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveNoFormat;
use PHPUnit\Framework\TestCase;

class PreserveNoFormatTest extends TestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PreserveNoFormat::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );

		$codeMacroProcessor = new PreserveNoFormat();
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
		$dir = dirname( dirname( __DIR__ ) );
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
