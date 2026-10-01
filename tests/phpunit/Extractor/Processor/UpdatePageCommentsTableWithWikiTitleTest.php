<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\UpdatePageCommentsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;

class UpdatePageCommentsTableWithWikiTitleTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\UpdatePageCommentsTableWithWikiTitle::execute
	 */
	public function testAddsTalkTitleForAllValidComments(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getPageComments' )->willReturn( [
			[
				'comment_id' => 100,
				'page_id' => 30,
			],
			[
				'comment_id' => 101,
				'page_id' => 31,
			],
		] );

		$workspaceDB->method( 'getWikiPageTitleFromPageId' )->willReturnMap( [
			[ 30, 'TEST:SamplePage' ],
			[ 31, 'TEST:SamplePage_2' ],
		] );

		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'updatePageCommentWikiTitle' )
			->withConsecutive(
				[ 100, 'TEST_Talk:SamplePage' ],
				[ 101, 'TEST_Talk:SamplePage_2' ]
			);

		$dbLog->expects( $this->never() )->method( 'addLogEntry' );

		$processor = new UpdatePageCommentsTableWithWikiTitle( $workspaceDB, $dbLog, $writer );
		$processor->execute();
	}
}
