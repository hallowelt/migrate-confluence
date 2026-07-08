<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractBlogPostsBodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents::execute
	 */
	public function testExtractsCurrentBlogPostBodyContent(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );

		$workspaceDB->method( 'getCurrentBlogPosts' )->willReturn( [ [ 'page_id' => 13 ] ] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )->with( 13 )->willReturn( [ 103 ] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )->with( 103 )->willReturn( 'Blog body' );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( '103', '<html><body>Blog body</body></html>' )
			->willReturn( '/content/raw/103.mraw' );

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractBlogPostsBodyContents( $workspaceDB, $workspace, $dbLog );
		$processor->execute();
	}
}
