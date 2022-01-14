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

		file_put_contents( '/tmp/expected.xml', $expectedOutput );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = $this->getProcessorToTest();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML();
		file_put_contents( '/tmp/actual.xml', $actualOutput );

		/* Issue with xml namespaces ac, ri, bs
		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
		*/
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
