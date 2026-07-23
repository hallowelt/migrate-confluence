<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ExcerptIncludeMacroTest extends ProcessorTestCase {
	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/PageExcerpt/excerpt-include-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/PageExcerpt/excerpt-include-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\ExcerptIncludeMacro::preprocess
	 * @return void
	 */
	public function testProcess() {
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
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
