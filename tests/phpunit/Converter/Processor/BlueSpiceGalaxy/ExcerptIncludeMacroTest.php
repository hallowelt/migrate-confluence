<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class ExcerptIncludeMacroTest extends ProcessorTestCase {
	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 3 ) . '/data/PageExcerpt/excerpt-include-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents(
			dirname( __DIR__, 3 ) . '/data/PageExcerpt/BlueSpiceGalaxy/excerpt-include-macro-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptIncludeMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$currentSpaceId = 42;

		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExcerptIncludeMacro( $dataLookup, $currentSpaceId, new PlaceholderManager() );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
