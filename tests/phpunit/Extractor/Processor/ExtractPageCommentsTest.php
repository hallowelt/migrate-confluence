<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageComments;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class ExtractPageCommentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageComments::execute
	 */
	public function testAddsTalkTitleForAllValidComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );

		$workspaceDB->method( 'getCommentsForPages' )->willReturn( [
			[
				'comment_id' => 100,
				'container_id' => 30,
				'wiki_title' => 'TEST:SamplePage',
			],
			[
				'comment_id' => 101,
				'container_id' => 31,
				'wiki_title' => 'TEST:SamplePage_2',
			],
		] );

		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'addPageComment' )
			->withConsecutive(
				[ 100, 30, 'TEST_Talk:SamplePage' ],
				[ 101, 31, 'TEST_Talk:SamplePage_2' ]
			);

		$dbLog->expects( $this->never() )->method( 'addLogEntry' );

		$processor = new ExtractPageComments( $workspaceDB, $dbLog );
		$processor->execute();
	}
}
