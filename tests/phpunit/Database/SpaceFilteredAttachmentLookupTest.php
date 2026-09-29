<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for space-filtered attachment lookups (used by the composer
 * when building per-namespace results). Older Confluence exports may leave
 * attachments.space_id NULL (no "space" property on the attachment object);
 * the space-filtered lookups must still find these attachments by falling
 * back to the space of their container page/blog post.
 */
class SpaceFilteredAttachmentLookupTest extends TestCase {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getPageAttachments
	 */
	public function testGetPageAttachmentsFallsBackToContainerPageSpaceWhenAttachmentSpaceIsNull(): void {
		$db = $this->createWorkspaceDB();
		$db->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$db->addPage( 600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );
		$db->addAttachment(
			601, null, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);
		$db->addPageAttachment( 601, 600, 'file.txt', 'TEST_Page-file.txt' );

		$result = $db->getPageAttachments( 1000 );

		$this->assertCount(
			1,
			$result,
			'Attachment without its own space_id must still be found via its container page space.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getBlogPostAttachments
	 */
	public function testGetBlogPostAttachmentsFallsBackToContainerBlogPostSpaceWhenAttachmentSpaceIsNull(): void {
		$db = $this->createWorkspaceDB();
		$db->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$db->addBlogPost( 700, 1000, 'Post', 'TEST:Post', 'current', '', '', '1', -1, [], [], [], [] );
		$db->addAttachment(
			701, null, 'file.txt', 'txt', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);
		$db->addBlogPostAttachment( 701, 700, 'file.txt', 'TEST_Post-file.txt' );

		$result = $db->getBlogPostAttachments( 1000 );

		$this->assertCount(
			1,
			$result,
			'Attachment without its own space_id must still be found via its container blog post space.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getAdditionalAttachments
	 */
	public function testGetAdditionalAttachmentsFallsBackToContainerPageSpaceWhenAttachmentSpaceIsNull(): void {
		$db = $this->createWorkspaceDB();
		$db->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$db->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );
		$db->addAttachment(
			803, null, 'orphan.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/e', [], [], []
		);
		$db->addAdditionalAttachment( 803, 'orphan.pdf', 'TEST_orphan.pdf' );

		$result = $db->getAdditionalAttachments( 1000 );

		$this->assertCount(
			1,
			$result,
			'Additional attachment without its own space_id must still be found via its container space.'
		);
	}
}
