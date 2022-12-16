<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroRecentlyUpdated;
use PHPUnit\Framework\TestCase;

class StructuredMacroRecentlyUpdatedTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroRecentlyUpdated::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new StructuredMacroRecentlyUpdated( 'ABC:SomePage_1' );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$output = $this->getExpectedOutput();

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $output );

		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrorecentlyupdated-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrorecentlyupdated-output.xml' );
	}
}
