<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\Processor\OfficeViewfileMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * @group full
 */
class OfficeViewfileMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\OfficeViewfileMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$inputXml = (string)file_get_contents( "$dir/view-file-alias-macro-input.xml" );
		$workspaceDb = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$processor = new OfficeViewfileMacro(
			$this->createConverterDataWriter(),
			new DBConversionDataLookup( $workspaceDb ),
			42,
			'SomePage',
			new MigrationConfig( [] )
		);

		$actual = $this->runChainWithProcessor( $processor, $inputXml );

		$this->assertStringContainsString( '{{ViewFile|filename = ', $actual );
		$this->assertStringContainsString( 'Dummy_2.docx}}', $actual );
	}
}
