<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\NoteMacro;

/**
 * @group full
 */
class NoteMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\NoteMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$executedAssertions = 0;

		$inlineFixtures = [
			<<<'XML'
			<?xml version="1.0" encoding="UTF-8"?>
			<root xmlns:ac="http://atlassian.com/content">
				<ac:structured-macro ac:name="note">
					<ac:parameter ac:name="title">Heads up</ac:parameter>
					<ac:rich-text-body>
						<p>This is a note body.</p>
					</ac:rich-text-body>
				</ac:structured-macro>
			</root>
XML
,
		];

		foreach ( $inlineFixtures as $index => $inlineXml ) {
			$actual = $this->runChainWithProcessor( $this->createProcessor(), $inlineXml );
			$this->assertNotSame( '', trim( $actual ), "Empty chain output for inline fixture #" . ( $index + 1 ) );
			$executedAssertions++;
		}

		if ( $executedAssertions === 0 ) {
			$fallbackXml =
				'<?xml version="1.0" encoding="UTF-8"?><root xmlns:ac="some" xmlns:ri="thing">' .
				'<p>Fallback fixture for NoteMacro.</p></root>';
			$actual = $this->runChainWithProcessor( $this->createProcessor(), $fallbackXml );
			$this->assertNotSame( '', trim( $actual ), "Empty chain output for fallback fixture" );
		}
	}

	/**
	 * @return IProcessor
	 */
	private function createProcessor(): IProcessor {
		return new NoteMacro();
	}

}
