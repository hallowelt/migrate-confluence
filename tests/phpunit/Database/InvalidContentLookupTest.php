<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use PHPUnit\Framework\TestCase;

class InvalidContentLookupTest extends TestCase {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	private function seedPages( WorkspaceDB $db ): void {
		// Current root with a historical revision
		$db->addPage(
			1, 42, 'Current', 'current', 'TEST:Current', 'current', '', '', '2', -1, -1, [ 10 ], [ 2 ], [], []
		);
		$db->addPage( 2, 42, 'Current', 'current', '', 'historical', '', '', '1', 1, -1, [ 11 ], [], [], [] );
		// Draft root with a historical revision
		$db->addPage( 3, 42, 'Draft', 'draft', '', 'draft', '', '', '2', -1, -1, [ 12 ], [ 4 ], [], [] );
		$db->addPage( 4, 42, 'Draft', 'draft', '', 'historical', '', '', '1', 3, -1, [ 13 ], [], [], [] );
		// Current root without any invalid body
		$db->addPage( 5, 42, 'Fine', 'fine', 'TEST:Fine', 'current', '', '', '1', -1, -1, [ 14 ], [], [], [] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getInvalidPages
	 */
	public function testInvalidBodyOfHistoricalRevisionMakesCurrentRootInvalid(): void {
		$db = $this->createWorkspaceDB();
		$this->seedPages( $db );
		$db->addInvalidBodyContent( 11, 'too long' );

		$invalid = $db->getInvalidPages();

		$this->assertCount( 1, $invalid );
		$this->assertSame( 1, (int)$invalid[0]['page_id'] );
		$this->assertSame( 'TEST:Current', $invalid[0]['wiki_title'] );
		$this->assertSame( 'too long', $invalid[0]['text'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getInvalidPages
	 */
	public function testInvalidBodyOfCurrentRootMakesItInvalid(): void {
		$db = $this->createWorkspaceDB();
		$this->seedPages( $db );
		$db->addInvalidBodyContent( 10, 'too long' );

		$invalid = $db->getInvalidPages();

		$this->assertCount( 1, $invalid );
		$this->assertSame( 1, (int)$invalid[0]['page_id'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getInvalidPages
	 */
	public function testDraftPagesAreNeverReportedAsInvalid(): void {
		$db = $this->createWorkspaceDB();
		$this->seedPages( $db );
		$db->addInvalidBodyContent( 12, 'too long' );
		$db->addInvalidBodyContent( 13, 'too long' );

		$this->assertSame( [], $db->getInvalidPages() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getInvalidBlogPosts
	 */
	public function testInvalidBlogPostsFollowTheSameRules(): void {
		$db = $this->createWorkspaceDB();
		$db->addBlogPost( 1, 42, 'Post', 'post', 'TEST:Blog/Post', 'current', '', '', '2', -1, [ 10 ], [ 2 ], [], [] );
		$db->addBlogPost( 2, 42, 'Post', 'post', '', 'historical', '', '', '1', 1, [ 11 ], [], [], [] );
		$db->addBlogPost( 3, 42, 'Draft', 'draft', '', 'draft', '', '', '1', -1, [ 12 ], [], [], [] );
		$db->addInvalidBodyContent( 11, 'too long' );
		$db->addInvalidBodyContent( 12, 'too long' );

		$invalid = $db->getInvalidBlogPosts();

		$this->assertCount( 1, $invalid );
		$this->assertSame( 1, (int)$invalid[0]['page_id'] );
		$this->assertSame( 'too long', $invalid[0]['text'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::isRootContentNonCurrent
	 */
	public function testIsRootContentNonCurrent(): void {
		$db = $this->createWorkspaceDB();
		$this->seedPages( $db );
		$db->addBlogPost( 1, 42, 'Draft', 'draft', '', 'draft', '', '', '1', -1, [ 12 ], [], [], [] );

		$this->assertFalse( $db->isRootContentNonCurrent( 'page', 1 ), 'current root' );
		$this->assertFalse( $db->isRootContentNonCurrent( 'page', 2 ), 'revision of current root' );
		$this->assertTrue( $db->isRootContentNonCurrent( 'page', 3 ), 'draft root' );
		$this->assertTrue( $db->isRootContentNonCurrent( 'page', 4 ), 'revision of draft root' );
		$this->assertFalse( $db->isRootContentNonCurrent( 'page', 999 ), 'unknown id' );
		$this->assertTrue( $db->isRootContentNonCurrent( 'blogPost', 1 ), 'draft blog post' );
	}
}
