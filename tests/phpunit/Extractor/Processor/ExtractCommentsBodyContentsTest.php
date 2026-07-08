<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractCommentsBodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents::execute
	 */
	public function testExtractsOnlyPageAndBlogPostComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		$dbLog = $this->createMock( DBLog::class );

		$workspaceDB->method( 'getCurrentComments' )->willReturn( [
			[ 'comment_id' => 14, 'content_class' => 'Page' ],
			[ 'comment_id' => 15, 'content_class' => 'BlogPost' ],
			[ 'comment_id' => 16, 'content_class' => 'SpaceDescription' ],
		] );
		$workspaceDB->method( 'getBodyContentIdsForContentId' )
			->willReturnCallback( static function ( int $contentId ) {
				if ( $contentId === 14 ) {
					return [ 104 ];
				}
				if ( $contentId === 15 ) {
					return [ 105 ];
				}
				return [];
			} );
		$workspaceDB->method( 'getBodyContentBodyByBodyContentId' )
			->willReturnCallback( static function ( int $bodyContentId ) {
				if ( $bodyContentId === 104 ) {
					return 'Comment page body';
				}
				if ( $bodyContentId === 105 ) {
					return 'Comment blog body';
				}
				return null;
			} );

		$workspace->expects( $this->exactly( 2 ) )
			->method( 'saveRawContent' )
			->withConsecutive(
				[ '104', '<html><body>Comment page body</body></html>' ],
				[ '105', '<html><body>Comment blog body</body></html>' ]
			)
			->willReturnOnConsecutiveCalls( '/content/raw/104.mraw', '/content/raw/105.mraw' );

		$dbLog->expects( $this->exactly( 2 ) )->method( 'addLogEntry' );

		$processor = new ExtractCommentsBodyContents( $workspaceDB, $workspace, $dbLog );
		$processor->execute();
	}
}
