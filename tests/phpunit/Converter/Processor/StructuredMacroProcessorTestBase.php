<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IStructuredMacroProcessorTest;
use HalloWelt\MigrateConfluence\Converter\Processor\MacroColumn;
use PHPUnit\Framework\TestCase;

abstract class MacroPanelTest extends TestCase implements IStructuredMacroProcessorTest {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\StructuredMacroProcessor::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new MacroColumn();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML();

		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
	}
}
