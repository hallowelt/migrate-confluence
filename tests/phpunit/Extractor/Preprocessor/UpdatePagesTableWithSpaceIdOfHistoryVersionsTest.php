<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithSpaceIdOfHistoryVersions;
use PHPUnit\Framework\TestCase;

class UpdatePagesTableWithSpaceIdOfHistoryVersionsTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithSpaceIdOfHistoryVersions::execute
	 */
	public function testUpdatesHistoricalPageSpaceIdFromOriginalVersion(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addPage( 100, 10, 'Original', '', 'current', '', '', '1', -1, -1, [], [], [], [] );
		$workspaceDB->addPage( 101, null, 'Historical', '', 'historical', '', '', '1', 100, -1, [], [], [], [] );

		$processor = new UpdatePagesTableWithSpaceIdOfHistoryVersions( $workspaceDB, $dbLog );
		$processor->execute();

		$historical = $this->findRowById( $workspaceDB->getPages(), 'page_id', 101 );
		$this->assertNotNull( $historical, 'Expected historical page row to exist.' );
		$this->assertSame( 10, $historical['space_id'], 'Expected space_id to be copied from original page.' );
	}
}
