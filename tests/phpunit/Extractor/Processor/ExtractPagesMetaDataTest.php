<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractPagesMetaDataTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData::execute
	 */
	public function testAddsPageMetaWithConfiguredAndLabelCategories(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );

		$migrationConfig->method( 'getCategories' )->willReturn( [ 'ConfiguredCategory' ] );
		$workspaceDB->method( 'getCurrentPages' )->willReturn( [
			[
				'page_id' => 30,
				'wiki_title' => 'TEST:Page',
				'original_version_id' => -1,
				'collection' => [ 'labellings' => [ 200 ] ],
			],
		] );
		$workspaceDB->method( 'getLabellingById' )->with( 200 )->willReturn( [ 'label_id' => 300 ] );
		$workspaceDB->method( 'getLabelById' )->with( 300 )->willReturn( [ 'name' => 'LabelCategory' ] );

		$workspaceDB->expects( $this->once() )
			->method( 'addPageMeta' )
			->with(
				30,
				[ 'categories' => [ 'ConfiguredCategory', 'LabelCategory' ] ]
			);

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractPagesMetaData( $workspaceDB, $dbLog, $migrationConfig );
		$processor->execute();
	}
}
