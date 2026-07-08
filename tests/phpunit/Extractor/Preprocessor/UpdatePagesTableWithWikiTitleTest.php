<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class UpdatePagesTableWithWikiTitleTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle::execute
	 */
	public function testBuildsWikiTitleForCurrentTopLevelPage(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			400, 42, 'Sample page', '', 'current', '', '', '1', -1, -1, [], [], [], []
		);

		$processor = new UpdatePagesTableWithWikiTitle( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$page = $this->findRowById( $workspaceDB->getPages(), 'page_id', 400 );
		$this->assertNotNull( $page, 'Expected page row to exist.' );
		$this->assertNotSame(
			'',
			$page['wiki_title'],
			'Expected wiki_title to be generated.'
		);
		$this->assertStringStartsWith(
			'TEST:',
			$page['wiki_title'],
			'Expected generated wiki_title to use TEST namespace.'
		);
	}
}
