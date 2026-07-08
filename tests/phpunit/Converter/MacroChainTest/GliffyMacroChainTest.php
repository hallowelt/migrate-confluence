<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro;

/**
 * @group full
 */
class GliffyMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'gliffy-macro-input.xml' => 'gliffy-macro-output.wikitext',
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
		$workspaceDb = ( new \HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock() )
			->createWithoutExtNsFileRepoCompat();
		$dataLookup = new \HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup( $workspaceDb );
		$pipe = fopen( 'php://temp', 'w+' );
		$pipeToDb = new \HalloWelt\MigrateConfluence\Utility\PipeToDB( $pipe );

		return new GliffyMacro( $dataLookup, 42, 'SomePage', $pipeToDb );
	}

}
