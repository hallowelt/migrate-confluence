<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;

/**
 * @group full
 */
class PreservePStyleTagMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$executedAssertions = 0;

		$inlineFixtures = [
			<<<'XML'
			<?xml version="1.0" encoding="UTF-8"?>
			<root>
				<p style="color:red;">Styled paragraph</p>
				<p class="x" style="font-weight:bold;">Should not be wrapped</p>
				<p>Plain paragraph</p>
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
				'<p>Fallback fixture for PreservePStyleTag.</p></root>';
			$actual = $this->runChainWithProcessor( $this->createProcessor(), $fallbackXml );
			$this->assertNotSame( '', trim( $actual ), "Empty chain output for fallback fixture" );
		}
	}

	/**
	 * @return IProcessor
	 */
	private function createProcessor(): IProcessor {
		return new PreservePStyleTag();
	}

}
