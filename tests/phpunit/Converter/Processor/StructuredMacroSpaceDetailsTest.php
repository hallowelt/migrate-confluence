<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroSpaceDetails;
use PHPUnit\Framework\TestCase;

class StructuredMacroSpaceDetailsTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroSpaceDetailsTest::process
	 * @return void
	 */
	public function testProcess() {
		/** SpaceId GENERAL */
		$this->doTest( 'structuredmacro-space-details-input.xml', 'structuredmacro-space-details-output.xml' );
	}

	/**
	 * @param string $input
	 * @param string $output
	 */
	private function doTest( $input, $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new StructuredMacroSpaceDetails();
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
