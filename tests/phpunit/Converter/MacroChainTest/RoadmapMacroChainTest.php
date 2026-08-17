<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

/**
 * @group full
 */
class RoadmapMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'roadmap-macro-input.xml' => 'roadmap-macro-output.wikitext',
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
		$workspaceDb = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDb );
		$dataWriter = new ConverterDirectDataWriter( $workspaceDb );
		$tmpBase = getenv( 'TMPDIR' ) ?: sys_get_temp_dir();
		$writerPath = $tmpBase . '/macro-chain-writer';
		if ( !is_dir( $writerPath ) ) {
			mkdir( $writerPath, 0755, true );
		}
		$conversionDataWriter = new ConversionDataWriter( $writerPath );

		return new RoadmapMacro( $dataLookup, $conversionDataWriter, $dataWriter, 42, 'SomePage' );
	}

}
