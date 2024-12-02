<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroInclude;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use PHPUnit\Framework\TestCase;

class StructuredMacroIncludeTest extends TestCase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacro-include-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacro-include-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\StructuredMacroProcessor::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		//TDB
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---drawio.png' => 'SomePage_drawio.png',
				'0---SomePage---SomeImage2.png' => 'SomePage_SomeImage2.png',
				'23---SomePage---drawio.png' => 'DEVOPS_SomePage_drawio.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[],
			[]
		);
		
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new StructuredMacroInclude( $dataLookup );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
