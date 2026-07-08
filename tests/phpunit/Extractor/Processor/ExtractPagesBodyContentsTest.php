<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractPagesBodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents::execute
	 */
	public function testExtractsCurrentPageBodyContent(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );

		$workspaceDB->method( 'getCurrentPages' )->willReturn( [ [ 'page_id' => 12 ] ] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )->with( 12 )->willReturn( [ 102 ] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )->with( 102 )->willReturn( 'Page body' );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( '102', '<html><body>Page body</body></html>' )
			->willReturn( '/content/raw/102.mraw' );

		$dbLog->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractPagesBodyContents( $workspaceDB, $workspace, $dbLog );
		$processor->execute();
	}
}
