<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents;
use PHPUnit\Framework\TestCase;

class ExtractSpaceDescriptionBodyContentsTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents::execute
	 */
	public function testExtractsCurrentSpaceDescriptionBodyContentToRawWorkspaceFile(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getCurrentSpaceDescriptions' )->willReturn( [
			[ 'space_description_id' => 11 ]
		] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )->with( 11 )->willReturn( [ 101 ] );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )
			->with( 101 )
			->willReturn( 'Body ]] > content' );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( '101', '<html><body>Body ]]> content</body></html>' )
			->willReturn( '/content/raw/101.mraw' );

		$workspaceDB->expects( $this->once() )
			->method( 'addLogEntry' )
			->with(
				$this->anything(),
				'INFO',
				'extract',
				$this->anything(),
				'Extract body content to /content/raw/101.mraw'
			);

		$processor = new ExtractSpaceDescriptionBodyContents( $workspaceDB, $workspace, $writer );
		$processor->execute();
	}
}
