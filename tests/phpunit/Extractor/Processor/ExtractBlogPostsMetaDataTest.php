<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractBlogPostsMetaDataTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData::execute
	 */
	public function testAddsBlogPostMetaFromLabellingCategories(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		$migrationConfig = $this->createMock( MigrationConfig::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$migrationConfig->method( 'getCategories' )->willReturn( [] );
		$workspaceDB->method( 'getCurrentBlogPosts' )->willReturn( [
			[
				'page_id' => 40,
				'wiki_title' => 'Blog:TEST/Entry',
				'original_version_id' => -1,
				'collection' => [ 'labellings' => [ 201 ] ],
			],
		] );
		$workspaceDB->method( 'getLabellingById' )->with( 201 )->willReturn( [ 'label_id' => 301 ] );
		$workspaceDB->method( 'getLabelById' )->with( 301 )->willReturn( [ 'name' => 'BlogLabel' ] );

		$writer->expects( $this->once() )
			->method( 'addBlogPostMeta' )
			->with( 40, [ 'categories' => [ 'BlogLabel' ] ] );

		$workspaceDB->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractBlogPostsMetaData( $workspaceDB, $writer, $migrationConfig );
		$processor->execute();
	}
}
