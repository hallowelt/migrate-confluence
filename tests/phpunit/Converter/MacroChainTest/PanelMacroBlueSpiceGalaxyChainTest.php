<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro;

/**
 * @group full
 */
class PanelMacroBlueSpiceGalaxyChainTest extends MacroChainTestBase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data/PanelMacro/BlueSpiceGalaxy';
		$inputXml = (string)file_get_contents( "$dir/panel-macro-input.xml" );
		$expected = (string)file_get_contents( "$dir/panel-macro-output.wikitext" );

		$actual = $this->runChainWithProcessor( new PanelMacro( $this->placeholderManager ), $inputXml );

		$this->assertSame( $expected, $actual );
	}
}
