<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Comments;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class CommentsTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Comments( new AnalyzeDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/comment_page_level.xml', 'Comment' );

		$comments = $this->workspaceDB->getComments();
		$this->assertCount( 1, $comments, 'Expected exactly one comment row.' );

		$comment = $comments[0];
		$this->assertSame( 600, $comment['comment_id'], 'Unexpected comment_id value.' );
		$this->assertSame( 700, $comment['container_id'], 'Unexpected container_id value.' );
		$this->assertSame( 'Page', $comment['content_class'], 'Unexpected content_class value.' );
		$this->assertSame( 'current', $comment['content_status'], 'Unexpected content_status value.' );
		$this->assertSame( 'userkey-abc', $comment['user_key'], 'Unexpected user_key value.' );
		$this->assertSame( '[]', $comment['body_content_ids'], 'Unexpected body_content_ids value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-02-12 17:09:43.563' ) ),
			$comment['created'],
			'Unexpected created timestamp value.'
		);
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-02-12 17:09:43.563' ) ),
			$comment['modified'],
			'Unexpected modified timestamp value.'
		);

		$properties = json_decode( $comment['properties'], true );
		$this->assertSame( 'current', $properties['contentStatus'], 'Unexpected properties.contentStatus value.' );
		$this->assertSame( 'userkey-abc', $properties['creator'], 'Unexpected properties.creator value.' );
		$this->assertSame( '700', $properties['containerContent'], 'Unexpected properties.containerContent value.' );
	}
}
