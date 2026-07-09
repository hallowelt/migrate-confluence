<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostComments;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractBlogPostCommentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostComments::execute
	 */
	public function testConvertsBlogNamespaceForAllValidComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );

		$workspaceDB->method( 'getCommentsForBlogPosts' )->willReturn( [
			[
				'comment_id' => 200,
				'container_id' => 40,
				'wiki_title' => 'Blog:TEST/Entry',
			],
			[
				'comment_id' => 201,
				'container_id' => 41,
				'wiki_title' => 'Blog:TEST/Entry_2',
			],
		] );

		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'addBlogPostComment' )
			->withConsecutive(
				[ 200, 40, 'Blog_Talk:TEST/Entry' ],
				[ 201, 41, 'Blog_Talk:TEST/Entry_2' ]
			);

		$dbLog->expects( $this->never() )->method( 'addLogEntry' );

		$processor = new ExtractBlogPostComments( $workspaceDB, $dbLog );
		$processor->execute();
	}
}
