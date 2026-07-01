<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractAttachmentsMetaDataTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData::execute
	 */
	public function testAddsAttachmentMetaFromLabellingCategories(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );

		$migrationConfig->method( 'getCategories' )->willReturn( [] );
		$workspaceDB->method( 'getCurrentAttachments' )->willReturn( [
			[
				'attachment_id' => 50,
				'wiki_title' => 'File:Sample.png',
				'original_version_id' => -1,
				'collection' => json_encode( [ 'labellings' => [ 202 ] ] ),
			],
		] );
		$workspaceDB->method( 'getLabellingById' )->with( 202 )->willReturn( [ 'label_id' => 302 ] );
		$workspaceDB->method( 'getLabelById' )->with( 302 )->willReturn( [ 'name' => 'AttachmentLabel' ] );

		$workspaceDB->expects( $this->once() )
			->method( 'addAttachmentMeta' )
			->with( 50, [ 'categories' => [ 'AttachmentLabel' ] ] );

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractAttachmentsMetaData( $workspaceDB, $dbLog, $migrationConfig );
		$processor->execute();
	}
}
