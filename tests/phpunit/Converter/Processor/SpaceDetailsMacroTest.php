<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\SpaceDetailsMacro;
use PHPUnit\Framework\TestCase;

class SpaceDetailsMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\SpaceDetailsMacroTest::process
	 * @return void
	 */
	public function testProcess() {
		/** SpaceId GENERAL */
		$this->doTest( 'space-details-macro-input.xml', 'space-details-macro-output.xml' );
	}

	/**
	 * @param string $input
	 * @param string $output
	 */
	private function doTest( $input, $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new SpaceDetailsMacro();
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
