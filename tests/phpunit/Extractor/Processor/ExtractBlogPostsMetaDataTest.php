<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractBlogPostsMetaDataTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData::execute
	 */
	public function testAddsBlogPostMetaFromLabellingCategories(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );

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

		$workspaceDB->expects( $this->once() )
			->method( 'addBlogPostMeta' )
			->with( 40, [ 'categories' => [ 'BlogLabel' ] ] );

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractBlogPostsMetaData( $workspaceDB, $dbLog, $migrationConfig );
		$processor->execute();
	}
}
