<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\TableChartMacro;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;

/**
 * @group full
 */
class TableChartMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableChartMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data/TableChart';
		$fixtures = [];
		foreach ( [ 'line', 'column', 'pie', 'unmapped-params', 'negative', 'radar' ] as $case ) {
			$fixtures["table-chart-macro-$case-input.xml"] = "table-chart-macro-$case-output.wikitext";
		}

		foreach ( $fixtures as $inputFixture => $expectedFixture ) {
			$inputPath = "$dir/$inputFixture";
			$expectedPath = "$dir/$expectedFixture";
			$this->assertFileExists( $inputPath, "Missing input fixture $inputFixture" );
			$this->assertFileExists( $expectedPath, "Missing expected fixture $expectedFixture" );
			$inputXml = (string)file_get_contents( $inputPath );
			$expected = $this->applyConfluenceFinalReplacements( (string)file_get_contents( $expectedPath ) );
			$actual = $this->runChainWithProcessor( $this->createProcessor(), $inputXml );
			$this->assertSame( $expected, $actual, "Mismatch for fixture $inputFixture" );
		}
	}

	/**
	 * @return IProcessor
	 */
	private function createProcessor(): IProcessor {
		return new TableChartMacro( $this->placeholderManager );
	}

	/**
	 * @param \DOMDocument $dom
	 * @return void
	 */
	protected function runUnhandledMacroProcessor( \DOMDocument $dom ): void {
		$unhandledMacroProcessor = new UnhandledMacroConverter( $this->placeholderManager );
		$unhandledMacroProcessor->process( $dom );
	}

}
