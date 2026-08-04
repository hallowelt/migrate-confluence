<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\Processor\LivesearchMacro;

class LivesearchMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\LivesearchMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( __DIR__, 2 ) . '/data/Livesearch';
		$input = file_get_contents( "$dir/livesearch-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new LivesearchMacro( $this->createMock( ConverterDirectDataWriter::class ) );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expected = file_get_contents( "$dir/livesearch-macro-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $expected );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
