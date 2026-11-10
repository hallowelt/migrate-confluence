<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents;
use PHPUnit\Framework\TestCase;

class ExtractPagesBodyContentsTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents::execute
	 */
	public function testExtractsCurrentPageBodyContent(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getCurrentPages' )->willReturn( [ [ 'page_id' => 12 ] ] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )->with( 12 )->willReturn( [ 102 ] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )->with( 102 )->willReturn( 'Page body' );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( '102', '<html><body>Page body</body></html>' )
			->willReturn( '/content/raw/102.mraw' );

		$workspaceDB->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractPagesBodyContents( $workspaceDB, $workspace, $writer );
		$processor->execute();
	}
}
