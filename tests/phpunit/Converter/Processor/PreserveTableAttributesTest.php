<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveTableAttributes;
use PHPUnit\Framework\TestCase;

class PreserveTableAttributesTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\RestoreTableAttributes::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$testDataDir = dirname( __DIR__ ) . '/../data';
		$input = file_get_contents( "$testDataDir/preservetableattributestest-input.xml" );
		$expectedOutput = file_get_contents( "$testDataDir/preservetableattributestest-output.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$preprocessor = new PreserveTableAttributes();
		$preprocessor->process( $dom );

		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$dom->saveXML()
		);
	}
}
