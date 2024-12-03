<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroExcerptInclude;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class StructuredMacroExcerptIncludeTest extends TestCase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacro-excerpt-include-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacro-excerpt-include-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\StructuredMacroExcerptInclude::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC'
			],
			[
				'42---Some Confluence page name' => 'ABC:Some_MediaWiki_page_name',
			],
			[],
			[],
			[],
			[]
		);
		$currentSpaceId = 42;

		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new StructuredMacroExcerptInclude( $dataLookup, $currentSpaceId );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
