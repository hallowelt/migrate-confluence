<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Macros;

use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros\Code;
use PHPUnit\Framework\TestCase;

class CodeTest extends TestCase {

	/**
	 * @param string $inputFile
	 * @param string $expectedOutputFile
	 * @covers HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros\Code::process
	 * @dataProvider provideTestProcessData
	 * @return void
	 */
	public function testProcess( $inputFile, $expectedOutputFile ) {
		$dom = new DOMDocument();
		$dom->load( $inputFile );
		$xpath = new DOMXPath( $dom );
		$match = $xpath->query( "//ac:structured-macro" )->item( 0 );

		$codeMacroProcessor = new Code();
		$codeMacroProcessor->process( null, $match, $dom, $xpath );

		$this->assertXmlStringEqualsXmlFile( $expectedOutputFile, $dom );
	}

	/**
	 *
	 * @return array
	 */
	public function provideTestProcessData() {
		return [
			'standard' => [
				__DIR__ . '/code_input_standard.xml',
				__DIR__ . '/code_expectation_standard.xml',
			],
			'no-body' => [
				__DIR__ . '/code_input_no_body.xml',
				__DIR__ . '/code_expectation_no_body.xml',
			]
		];
	}
}