<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents;
use PHPUnit\Framework\TestCase;

class ExtractCommentsBodyContentsTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents::execute
	 */
	public function testExtractsOnlyPageAndBlogPostComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$workspace = $this->createMock( Workspace::class );
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getComments' )->willReturn( [
			[
				'comment_id' => 14,
				'container_id' => 1,
				'content_status' => 'current',
				'body_content_ids' => json_encode( [ 104 ] ),
				'properties' => json_encode( [] ),
				'collection' => json_encode( [] ),
				'created' => '',
			],
			[
				'comment_id' => 15,
				'container_id' => 2,
				'content_status' => 'current',
				'body_content_ids' => json_encode( [ 105 ] ),
				'properties' => json_encode( [] ),
				'collection' => json_encode( [] ),
				'created' => '',
			],
			[
				// Inline comment on a page: must be excluded even though its container is a page.
				'comment_id' => 17,
				'container_id' => 1,
				'content_status' => 'current',
				'body_content_ids' => json_encode( [ 107 ] ),
				'properties' => json_encode( [] ),
				'collection' => json_encode( [ 'contentProperties' => [ 1 ] ] ),
				'created' => '',
			],
			[
				// Comment on neither a page nor a blog post: must be excluded.
				'comment_id' => 16,
				'container_id' => 3,
				'content_status' => 'current',
				'body_content_ids' => json_encode( [ 106 ] ),
				'properties' => json_encode( [] ),
				'collection' => json_encode( [] ),
				'created' => '',
			],
		] );
		$workspaceDB->method( 'pageIdExists' )->willReturnCallback(
			static fn ( int $pageId ) => $pageId === 1
		);
		$workspaceDB->method( 'blogPostIdExists' )->willReturnCallback(
			static fn ( int $blogPostId ) => $blogPostId === 2
		);
		$workspaceDB->method( 'getContentPopertyById' )->willReturnCallback(
			static function ( int $id ) {
				if ( $id === 1 ) {
					return [ 'properties' => json_encode( [ 'name' => 'inline-comment', 'stringValue' => 'true' ] ) ];
				}
				return null;
			}
		);
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

		$workspaceDB->expects( $this->exactly( 2 ) )->method( 'addLogEntry' );

		$processor = new ExtractCommentsBodyContents( $workspaceDB, $workspace, $writer );
		$processor->execute();
	}
}
