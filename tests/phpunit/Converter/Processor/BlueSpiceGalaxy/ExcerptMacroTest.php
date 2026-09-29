<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class ExcerptMacroTest extends ProcessorTestCase {

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 3 ) . '/data/PageExcerpt/excerpt-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents(
			dirname( __DIR__, 3 ) . '/data/PageExcerpt/BlueSpiceGalaxy/excerpt-macro-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptMacro::process
	 * @return void
	 */
	public function testProcess() {
		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExcerptMacro( new PlaceholderManager() );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
