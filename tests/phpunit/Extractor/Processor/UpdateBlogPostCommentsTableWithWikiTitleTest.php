<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\UpdateBlogPostCommentsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class UpdateBlogPostCommentsTableWithWikiTitleTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\UpdateBlogPostCommentsTableWithWikiTitle::execute
	 */
	public function testConvertsBlogNamespaceForAllValidComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getBlogPostComments' )->willReturn( [
			[
				'comment_id' => 200,
				'blog_post_id' => 40,
			],
			[
				'comment_id' => 201,
				'blog_post_id' => 41,
			],
		] );

		$workspaceDB->method( 'getWikiBlogPostTitleFromBlogPostId' )->willReturnMap( [
			[ 40, 'Blog:TEST/Entry' ],
			[ 41, 'Blog:TEST/Entry_2' ],
		] );

		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'updateBlogPostCommentWikiTitle' )
			->withConsecutive(
				[ 200, 'Blog_Talk:TEST/Entry' ],
				[ 201, 'Blog_Talk:TEST/Entry_2' ]
			);

		$dbLog->expects( $this->never() )->method( 'addLogEntry' );

		$processor = new UpdateBlogPostCommentsTableWithWikiTitle( $workspaceDB, $dbLog, $writer );
		$processor->execute();
	}
}
