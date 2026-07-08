<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsSummaryMacro;

class DetailsSummaryMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DetailsSummaryMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname(  __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/details-summary-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DetailsSummaryMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/details-summary-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
