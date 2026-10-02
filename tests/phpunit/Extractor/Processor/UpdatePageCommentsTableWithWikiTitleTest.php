<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\UpdatePageCommentsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
		$workspaceDB->method( 'getPageCommentWikiTitles' )->willReturn( [] );

		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'updatePageCommentWikiTitle' )
			->withConsecutive(
				[ 100, 'TEST_Talk:SamplePage' ],
				[ 101, 'TEST_Talk:SamplePage_2' ]
			)
			->willReturn( true );

		$dbLog->expects( $this->never() )->method( 'addLogEntry' );

		$processor = new UpdatePageCommentsTableWithWikiTitle( $workspaceDB, $dbLog, $writer );
		$processor->execute();
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Extractor\\Processor\\UpdatePageCommentsTableWithWikiTitle::execute
	 */
	public function testMakesGeneratedTalkTitlesUnique(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getPageComments' )->willReturn( [
			[ 'comment_id' => 100, 'page_id' => 30 ],
			[ 'comment_id' => 101, 'page_id' => 30 ],
		] );
		$workspaceDB->method( 'getWikiPageTitleFromPageId' )->with( 30 )->willReturn( 'TEST:SamplePage' );
		$workspaceDB->method( 'getPageCommentWikiTitles' )->willReturn( [] );
		$workspaceDB->expects( $this->exactly( 2 ) )
			->method( 'updatePageCommentWikiTitle' )
			->withConsecutive(
				[ 100, 'TEST_Talk:SamplePage' ],
				[ 101, 'TEST_Talk:SamplePage-(1)' ]
			)
			->willReturn( true );

		$processor = new UpdatePageCommentsTableWithWikiTitle( $workspaceDB, $dbLog, $writer );
		$processor->execute();
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Extractor\\Processor\\UpdatePageCommentsTableWithWikiTitle::execute
	 */
	public function testFailsWhenWikiTitleCannotBePersisted(): void {
		$workspaceDB = $this->createMock( WorkspaceDB::class );
		$dbLog = $this->createMock( DBLog::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getPageComments' )->willReturn( [
			[ 'comment_id' => 100, 'page_id' => 30 ],
		] );
		$workspaceDB->method( 'getWikiPageTitleFromPageId' )->with( 30 )->willReturn( 'TEST:SamplePage' );
		$workspaceDB->method( 'getPageCommentWikiTitles' )->willReturn( [] );
		$workspaceDB->expects( $this->once() )
			->method( 'updatePageCommentWikiTitle' )
			->with( 100, 'TEST_Talk:SamplePage' )
			->willReturn( false );
		$dbLog->expects( $this->once() )->method( 'addLogEntry' )->with(
			'error',
			'extract',
			UpdatePageCommentsTableWithWikiTitle::class,
			'Could not persist wiki title for page comment ID 100'
		);

		$this->expectException( RuntimeException::class );
		$processor = new UpdatePageCommentsTableWithWikiTitle( $workspaceDB, $dbLog, $writer );
		$processor->execute();
	}

}
