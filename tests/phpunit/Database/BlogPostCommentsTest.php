<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getBlogPostComments
 */
class BlogPostCommentsTest extends TestCase {

	public function testReturnsCommentDataForBlogPostComments(): void {
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
		$db->addComment(
			200,
			100,
			'BlogPost',
			'current',
			'abc123',
			[ 300 ],
			'20240302000000',
			'20240303000000',
			[ 'parent' => 10 ],
			[ 'contentProperties' => [ 20 ] ]
		);
		$db->addBlogPostComment( 200, 100, 'Blog_Talk:MKT/My_Blog_Post' );

		$blogPostComments = $db->getBlogPostComments();

		$this->assertCount( 1, $blogPostComments );
		$this->assertSame( 200, $blogPostComments[0]['comment_id'] );
		$this->assertSame( 100, $blogPostComments[0]['blog_post_id'] );
		$this->assertSame( 'Blog_Talk:MKT/My_Blog_Post', $blogPostComments[0]['wiki_title'] );
		$this->assertSame( 100, $blogPostComments[0]['container_id'] );
		$this->assertSame( 'BlogPost', $blogPostComments[0]['content_class'] );
		$this->assertSame( 'current', $blogPostComments[0]['content_status'] );
		$this->assertSame( 'abc123', $blogPostComments[0]['user_key'] );
		$this->assertSame( '[300]', $blogPostComments[0]['body_content_ids'] );
		$this->assertSame( '20240302000000', $blogPostComments[0]['created'] );
		$this->assertSame( '20240303000000', $blogPostComments[0]['modified'] );
	}
}
