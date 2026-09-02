<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataReader\ExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractBlogPostsBodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents::execute
	 */
	public function testExtractsCurrentBlogPostBodyContent(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dataReader = new ExtractorDataReader( $workspaceDB );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getBlogPosts' )->willReturn( [ [ 'page_id' => 13 ] ] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )->with( 13 )->willReturn( [ 103 ] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )->with( 103 )->willReturn( 'Blog body' );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( '103', '<html><body>Blog body</body></html>' )
			->willReturn( '/content/raw/103.mraw' );

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractBlogPostsBodyContents( $workspaceDB, $workspace, $dbLog, $writer, $dataReader );
		$processor->execute();
	}

	/**
	 * Historical blog post versions have content_status = 'draft' in Confluence exports.
	 * They must be extracted so the composer can build revision history.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents::execute
	 */
	public function testExtractsHistoricalBlogPostBodyContent(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dataReader = new ExtractorDataReader( $workspaceDB );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		// Both the current version (page_id=20) and a historical draft (page_id=19)
		// are returned by getBlogPosts().
		$workspaceDB->method( 'getBlogPosts' )->willReturn( [
			[ 'page_id' => 20 ],
			[ 'page_id' => 19 ],
		] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )
			->willReturnMap( [
				[ 20, [ 200 ] ],
				[ 19, [ 190 ] ],
			] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )
			->willReturnMap( [
				[ 200, 'Current body' ],
				[ 190, 'Historical body' ],
			] );

		$workspace->expects( $this->exactly( 2 ) )
			->method( 'saveRawContent' )
			->willReturn( '/content/raw/x.mraw' );

		$processor = new ExtractBlogPostsBodyContents( $workspaceDB, $workspace, $dbLog, $writer, $dataReader );
		$processor->execute();
	}
}
