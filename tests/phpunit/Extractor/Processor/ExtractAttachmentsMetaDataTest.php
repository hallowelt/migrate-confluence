<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractAttachmentsMetaDataTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData::execute
	 */
	public function testAddsAttachmentMetaFromLabellingCategories(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );
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

		$writer->expects( $this->once() )
			->method( 'addAttachmentMeta' )
			->with( 50, [ 'categories' => [ 'AttachmentLabel' ] ] );

		$workspaceDB->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractAttachmentsMetaData( $workspaceDB, $writer, $migrationConfig );
		$processor->execute();
	}
}
