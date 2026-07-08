<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractSpaceDescriptionBodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents::execute
	 */
	public function testExtractsCurrentSpaceDescriptionBodyContentToRawWorkspaceFile(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );

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

		$class = 'HalloWelt\\MigrateConfluence\\Extractor\\Processor\\';
		$class .= 'ExtractSpaceDescriptionBodyContents::doExtractBodyContent';

		$dbLog->expects( $this->once() )
			->method( 'addLogEntry' )
			->with(
				'info',
				'extract',
				$class,
				'Extract body content to /content/raw/101.mraw'
			);

		$processor = new ExtractSpaceDescriptionBodyContents( $workspaceDB, $workspace, $dbLog );
		$processor->execute();
	}
}
