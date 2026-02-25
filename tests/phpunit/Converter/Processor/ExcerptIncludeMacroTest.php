<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class ExcerptIncludeMacroTest extends TestCase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/excerpt-include-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/excerpt-include-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\ExcerptIncludeMacro::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC:'
			],
			[
				'42---Some Confluence page name' => 'ABC:Some_MediaWiki_page_name',
			],
			[],
			[],
			[],
			[],
			[
				42 => 'ABC',
			]
		);
		$currentSpaceId = 42;

		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExcerptIncludeMacro( $dataLookup, $currentSpaceId );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
