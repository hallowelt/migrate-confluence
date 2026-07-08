<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor\PreprocessorTestHelper;
use PHPUnit\Framework\TestCase;

class UpdateBlogPostsTableWithSpaceIdOfHistoryVersionsTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithSpaceIdOfHistoryVersions::execute
	 */
	public function testUpdatesHistoricalBlogPostSpaceIdFromOriginalVersion(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addBlogPost( 200, 10, 'Original blog', '', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addBlogPost( 201, null, 'Historical blog', '', 'historical', '', '', '1', 200, [], [], [], [] );

		$processor = new UpdateBlogPostsTableWithSpaceIdOfHistoryVersions( $workspaceDB, $dbLog );
		$processor->execute();

		$historical = $this->findRowById( $workspaceDB->getBlogPosts(), 'page_id', 201 );
		$this->assertNotNull( $historical, 'Expected historical blog post row to exist.' );
		$this->assertSame( 10, $historical['space_id'], 'Expected space_id to be copied from original blog post.' );
	}
}
