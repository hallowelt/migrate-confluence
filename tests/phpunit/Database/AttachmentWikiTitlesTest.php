<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use PHPUnit\Framework\TestCase;

class AttachmentWikiTitlesTest extends TestCase {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Database\\WorkspaceDB::getPageAttachmentWikiTitles
	 */
	public function testReturnsDistinctNonEmptyPageAttachmentTitles(): void {
		$db = $this->createWorkspaceDB();
		$db->addPageAttachment( 1, 100, 'one.png', 'File:one.png' );
		$db->addPageAttachment( 2, 101, 'one.png', 'File:one.png' );
		$db->addPageAttachment( 3, 102, 'empty.png', '' );

		$this->assertSame( [ 'File:one.png' ], $db->getPageAttachmentWikiTitles() );
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Database\\WorkspaceDB::getBlogPostAttachmentWikiTitles
	 */
	public function testReturnsDistinctNonEmptyBlogPostAttachmentTitles(): void {
		$db = $this->createWorkspaceDB();
		$db->addBlogPostAttachment( 1, 100, 'one.png', 'File:one.png' );
		$db->addBlogPostAttachment( 2, 101, 'one.png', 'File:one.png' );
		$db->addBlogPostAttachment( 3, 102, 'empty.png', '' );

		$this->assertSame( [ 'File:one.png' ], $db->getBlogPostAttachmentWikiTitles() );
	}
}
