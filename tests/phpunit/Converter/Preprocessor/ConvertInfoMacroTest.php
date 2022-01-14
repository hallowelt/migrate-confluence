<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertInfoMacro;
use PHPUnit\Framework\TestCase;

class ConvertInfoMacroTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\ConvertInfoMacro::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$testDataDir = dirname( __DIR__ ) . '/../data';
		$input = file_get_contents( "$testDataDir/convertinfomacrotest-input.xml" );
		$expectedOutput = file_get_contents( "$testDataDir/convertinfomacrotest-output.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$preprocessor = new ConvertInfoMacro();
		$preprocessor->process( $dom );

		$actualOutput = $dom->saveXML();

		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
	}
}
