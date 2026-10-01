<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getPageComments
 */
class PageCommentsTest extends TestCase {

	public function testReturnsCommentDataForPageComments(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addPage(
			100,
			1,
			'My Page',
			'MKT:My_Page',
			'current',
			'20240301000000',
			'',
			'1',
			-1,
			-1,
			[],
			[],
			[],
			[]
		);
		$db->addComment(
			200,
			100,
			'Page',
			'current',
			'abc123',
			[ 300 ],
			'20240302000000',
			'20240303000000',
			[ 'parent' => 10 ],
			[ 'contentProperties' => [ 20 ] ]
		);
		$db->addPageComment( 200, 100, 'Talk:MKT:My_Page' );

		$pageComments = $db->getPageComments();

		$this->assertCount( 1, $pageComments );
		$this->assertSame( 200, $pageComments[0]['comment_id'] );
		$this->assertSame( 100, $pageComments[0]['page_id'] );
		$this->assertSame( 'Talk:MKT:My_Page', $pageComments[0]['wiki_title'] );
		$this->assertSame( 100, $pageComments[0]['container_id'] );
		$this->assertSame( 'Page', $pageComments[0]['content_class'] );
		$this->assertSame( 'current', $pageComments[0]['content_status'] );
		$this->assertSame( 'abc123', $pageComments[0]['user_key'] );
		$this->assertSame( '[300]', $pageComments[0]['body_content_ids'] );
		$this->assertSame( '20240302000000', $pageComments[0]['created'] );
		$this->assertSame( '20240303000000', $pageComments[0]['modified'] );
	}
}
