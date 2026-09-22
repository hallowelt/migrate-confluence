<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ChartMacro;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;

/**
 * @group full
 */
class ChartMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ChartMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'chart-macro-area-input.xml' => 'chart-macro-area-output.wikitext',
			'chart-macro-pie-input.xml' => 'chart-macro-pie-output.wikitext',
			'chart-macro-bar-input.xml' => 'chart-macro-bar-output.wikitext',
			'chart-macro-line-input.xml' => 'chart-macro-line-output.wikitext',
			'chart-macro-scatter-input.xml' => 'chart-macro-scatter-output.wikitext',
			'chart-macro-gantt-input.xml' => 'chart-macro-gantt-output.wikitext',
		];

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
		return new ChartMacro( $this->placeholderManager );
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
