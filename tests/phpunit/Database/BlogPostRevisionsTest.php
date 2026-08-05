<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getBlogPostRevisionsForBlogPostId
 */
class BlogPostRevisionsTest extends TestCase {

	public function testCurrentVersionIsReturned(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addBlogPost(
			100, 1, 'My Post', 'Blog:MKT/My_Post',
			'current', '20240301000000', '', '1', -1, [], [], [], []
		);

		$revisions = $db->getBlogPostRevisionsForBlogPostId( 100 );
		$this->assertCount( 1, $revisions );
		$this->assertSame( '20240301000000', $revisions[0]['revision_timestamp'] );
	}

	/**
	 * In Confluence exports, historical blog post versions have content_status = 'draft'
	 * and original_version_id pointing at the canonical (current) page_id.
	 * The composer needs these to build revision history.
	 */
	public function testHistoricalDraftVersionIsReturned(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		// Historical version (old revision) stored as draft
		$db->addBlogPost(
			99, 1, 'My Post', 'Blog:MKT/My_Post',
			'draft', '20240201000000', '', '1', 100, [], [], [], []
		);
		// Current version
		$db->addBlogPost(
			100, 1, 'My Post', 'Blog:MKT/My_Post',
			'current', '20240301000000', '', '2', -1, [], [], [], []
		);

		$revisions = $db->getBlogPostRevisionsForBlogPostId( 100 );
		$this->assertCount( 2, $revisions );
		$timestamps = array_column( $revisions, 'revision_timestamp' );
		$this->assertContains( '20240201000000', $timestamps );
		$this->assertContains( '20240301000000', $timestamps );
	}

	/**
	 * A standalone unpublished draft (original_version_id = -1, content_status = 'draft')
	 * must not appear in the revisions of any other post.
	 */
	public function testStandaloneDraftIsNotReturnedForOtherPost(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		// Standalone unpublished draft — unrelated to post 100
		$db->addBlogPost(
			77, 1, 'Unpublished', 'Blog:MKT/Unpublished',
			'draft', '20240101000000', '', '1', -1, [], [], [], []
		);
		// Current post
		$db->addBlogPost(
			100, 1, 'My Post', 'Blog:MKT/My_Post',
			'current', '20240301000000', '', '1', -1, [], [], [], []
		);

		$revisions = $db->getBlogPostRevisionsForBlogPostId( 100 );
		$this->assertCount( 1, $revisions );
		$this->assertSame( '20240301000000', $revisions[0]['revision_timestamp'] );
	}
}
