<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use PHPUnit\Framework\TestCase;

class PageWikiTitlesTest extends TestCase {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Database\\WorkspaceDB::getPageWikiTitles
	 */
	public function testReturnsDistinctNonEmptyPageWikiTitles(): void {
		$db = $this->createWorkspaceDB();
		$db->addPage( 1, null, 'Page one', 'page one', 'TEST:One', 'current', '', '', '1', -1, -1, [], [], [], [] );
		$db->addPage(
			2, null, 'Page one historical', 'page one', 'TEST:One', 'current', '', '', '1', 1, -1, [], [], [], []
		);
		$db->addPage( 3, null, 'Page without title', '', '', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$this->assertSame( [ 'TEST:One' ], $db->getPageWikiTitles() );
	}
}
