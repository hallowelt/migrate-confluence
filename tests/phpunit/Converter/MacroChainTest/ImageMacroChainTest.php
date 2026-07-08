<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;

/**
 * @group full
 */
class ImageMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Image::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'image-attachment-input-1.xml' => 'image-attachment-input-1.wikitext',
			'image-attachment-input-2.xml' => 'image-attachment-input-2.wikitext',
			'image-url-external-link-input.xml' => 'image-url-external-link-output.wikitext',
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
		$migrationConfig = new \HalloWelt\MigrateConfluence\Utility\MigrationConfig( [] );

		return new Image( $dataLookup, 42, 'SomePage', $migrationConfig );
	}

}
