<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use PHPUnit\Framework\TestCase;

abstract class StructuredMacroProcessorTestBase extends TestCase {

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
	public function testPreprocess() {
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = $this->getProcessorToTest();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML();

		/* Issue with xml namespaces ac, ri, bs
		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
		*/
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
