<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::commentIdExists
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::pageCommentIdExists
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::blogPostCommentIdExists
 */
class CommentIdExistsTest extends TestCase {

	public function testPageCommentIdExistsReturnsTrueForPageComment(): void {
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
		$db->addComment( 200, 100, 'Page', 'current', 'abc123', [], '', '', [], [] );
		$db->addPageComment( 200, 100, 'Talk:MKT:My_Page' );

		$this->assertTrue( $db->pageCommentIdExists( 200 ) );
		$this->assertFalse( $db->blogPostCommentIdExists( 200 ) );
	}

	public function testBlogPostCommentIdExistsReturnsTrueForBlogPostComment(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addBlogPost(
			100,
			1,
			'My Blog Post',
			'Blog:MKT/My_Blog_Post',
			'current',
			'20240301000000',
			'',
			'1',
			-1,
			[],
			[],
			[],
			[]
		);
		$db->addComment( 200, 100, 'BlogPost', 'current', 'abc123', [], '', '', [], [] );
		$db->addBlogPostComment( 200, 100, 'Blog_Talk:MKT/My_Blog_Post' );

		$this->assertFalse( $db->pageCommentIdExists( 200 ) );
		$this->assertTrue( $db->blogPostCommentIdExists( 200 ) );
	}

	public function testRelationMethodsReturnFalseForCommentWithoutPageOrBlogPostRelation(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addComment( 200, 100, 'Comment', 'current', 'abc123', [], '', '', [], [] );

		$this->assertFalse( $db->pageCommentIdExists( 200 ) );
		$this->assertFalse( $db->blogPostCommentIdExists( 200 ) );
	}

	public function testCommentIdExistsStillChecksCommentsTable(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addComment( 200, 100, 'Comment', 'current', 'abc123', [], '', '', [], [] );

		$this->assertTrue( $db->commentIdExists( 200 ) );
	}
}
