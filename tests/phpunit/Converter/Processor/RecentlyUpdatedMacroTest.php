<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\RecentlyUpdatedMacro;
use PHPUnit\Framework\TestCase;

class RecentlyUpdatedMacroTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RecentlyUpdatedMacro::preprocess
	 * @return void
	 */
	public function testPreprocess() {
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
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/recently-updated-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/recently-updated-macro-output.xml' );
	}
}
