<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\Processor\CsvTableMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\JsonFromTableMacro;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * @group full
 */
class SpreadsheetTableMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\SpreadsheetTableMacroBase::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'spreadsheet-table-macro-input.xml' => 'spreadsheet-table-macro-output.wikitext',
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
	 * @return array
	 */
	private function createProcessors(): array {
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );
		$migrationConfig = new MigrationConfig( [] );

		return [
			new CsvTableMacro( $dataLookup, 23, 'SomePage', $migrationConfig ),
			new JsonFromTableMacro( $dataLookup, 23, 'SomePage', $migrationConfig ),
		];
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
