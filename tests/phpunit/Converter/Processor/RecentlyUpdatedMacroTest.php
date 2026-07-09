<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\RecentlyUpdatedMacro;

class RecentlyUpdatedMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RecentlyUpdatedMacro::preprocess
	 * @return void
	 */
	public function testProcess() {
		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new RecentlyUpdatedMacro( 'ABC:SomePage_1' );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$output = $this->getExpectedOutput();

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $output );

		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/recently-updated-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/recently-updated-macro-output.xml' );
	}
}
