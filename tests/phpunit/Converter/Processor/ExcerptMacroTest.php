<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro;

class ExcerptMacroTest extends ProcessorTestCase {

	protected function getInput(): string {
		return file_get_contents( dirname(  __DIR__, 2 ) . '/data/excerpt-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname(  __DIR__, 2 ) . '/data/excerpt-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro::preprocess
	 * @return void
	 */
	public function testProcess() {
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExcerptMacro();
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
