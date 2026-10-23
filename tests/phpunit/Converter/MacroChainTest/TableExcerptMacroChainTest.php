<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptMacro;

/**
 * @group full
 */
class TableExcerptMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data/PageExcerpt';
		$fixtures = [
			'table-excerpt-macro-input.xml' => 'BlueSpiceGalaxy/table-excerpt-macro-output.wikitext',
		];

		foreach ( $fixtures as $inputFixture => $expectedFixture ) {
			$inputPath = "$dir/$inputFixture";
			$expectedPath = "$dir/$expectedFixture";
			$this->assertFileExists( $inputPath, "Missing input fixture $inputFixture" );
			$this->assertFileExists( $expectedPath, "Missing expected fixture $expectedFixture" );
			$inputXml = (string)file_get_contents( $inputPath );
			$expected = $this->applyConfluenceFinalReplacements( (string)file_get_contents( $expectedPath ) );
			$actual = $this->runChainWithProcessor( $this->createProcessors(), $inputXml );
			$this->assertSame( $expected, $actual, "Mismatch for fixture $inputFixture" );
		}
	}

	/**
	 * @return IProcessor[]
	 */
	private function createProcessors(): array {
		return [
			new ExcerptMacro( $this->placeholderManager ),
			new TableExcerptMacro( $this->placeholderManager ),
		];
	}

}
