<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * @group full
 */
class GalleryMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'gallery-macro-broken-input.xml' => 'gallery-macro-broken-output.wikitext',
			'gallery-macro-input.xml' => 'gallery-macro-output.wikitext',
			'gallery-macro-label-input.xml' => 'gallery-macro-label-output.wikitext',
			'gallery-macro-page-input.xml' => 'gallery-macro-page-output.wikitext',
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
		$workspaceDb = ( new WorkspaceDbMock() )
			->createWithoutExtNsFileRepoCompat();
		$dataReader = new ConverterDirectDataReader( $workspaceDb );
		$migrationConfig = new MigrationConfig( [] );

		return new GalleryMacro( $dataReader, 42, 'SomePage', $migrationConfig );
	}

}
