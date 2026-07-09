<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

abstract class StructuredMacroProcessorTestBase extends ProcessorTestCase {

	/**
	 *
	 * @return string
	 */
	abstract protected function getInput(): string;

	/**
	 *
	 * @return string
	 */
	abstract protected function getExpectedOutput(): string;

	/**
	 *
	 * @return IProcessor
	 */
	abstract protected function getProcessorToTest(): IProcessor;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\StructuredMacroProcessor::preprocess
	 * @return void
	 */
	public function testProcess() {
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = $this->getProcessorToTest();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
