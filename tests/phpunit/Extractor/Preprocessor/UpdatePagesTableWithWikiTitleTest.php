<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use PHPUnit\Framework\TestCase;

class UpdatePagesTableWithWikiTitleTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle::execute
	 */
	public function testBuildsWikiTitleForCurrentTopLevelPage(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addWikisConfig( 'TEST', 'test-wiki', 'TEST', '' );
		$workspaceDB->addPage(
			400, 42, 'Sample page', 'sample page', '', 'current', '', '', '1', -1, -1, [], [], [], []
		);

		$processor = new UpdatePagesTableWithWikiTitle(
			$workspaceDB,
			$dbLog,
			$writer,
			new MigrationConfig( [] ),
			new WikisConfig( $workspaceDB )
		);
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

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle::execute
	 */
	public function testEachWikiKeepsItsOwnMainPageWhenMigratingMultipleWikis(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		// Two spaces mapped (via --wikis) to two different output wikis, both without a
		// namespace/root page, so both homepages resolve to the literal main page name.
		$workspaceDB->addSpace( 42, 'ONE', 'Space One', '', '', '', 100, -1 );
		$workspaceDB->addSpace( 43, 'TWO', 'Space Two', '', '', '', 200, -1 );
		$workspaceDB->addWikisConfig( 'ONE', 'wiki-one', '', '' );
		$workspaceDB->addWikisConfig( 'TWO', 'wiki-two', '', '' );

		$workspaceDB->addPage(
			100, 42, 'Home', 'home', '', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addPage(
			200, 43, 'Home', 'home', '', 'current', '', '', '1', -1, -1, [], [], [], []
		);

		$processor = new UpdatePagesTableWithWikiTitle(
			$workspaceDB,
			$dbLog,
			$writer,
			new MigrationConfig( [] ),
			new WikisConfig( $workspaceDB )
		);
		$processor->execute();

		$pageOne = $this->findRowById( $workspaceDB->getPages(), 'page_id', 100 );
		$pageTwo = $this->findRowById( $workspaceDB->getPages(), 'page_id', 200 );

		$this->assertSame(
			'Main_Page',
			$pageOne['wiki_title'],
			'Expected first wiki homepage to keep the plain main page title.'
		);
		$this->assertSame(
			'Main_Page',
			$pageTwo['wiki_title'],
			'Expected second wiki homepage to keep the plain main page title ' .
			'instead of being uncollided against the other wiki.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle::execute
	 */
	public function testCurrentPageIsNotSuffixedDueToCollidingWithDeletedPages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addWikisConfig( 'TEST', 'test-wiki', 'TEST', '' );

		// Two trashed/deleted pages that share a title with a later, still-current page.
		// Deleted content is never composed into the final wiki output, so it must not
		// occupy/consume the clean (unsuffixed) title that the current page needs.
		$workspaceDB->addPage(
			100, 42, 'Sample page', 'sample page', '', 'deleted', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addPage(
			200, 42, 'Sample page', 'sample page', '', 'deleted', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addPage(
			300, 42, 'Sample page', 'sample page', '', 'current', '', '', '1', -1, -1, [], [], [], []
		);

		$processor = new UpdatePagesTableWithWikiTitle(
			$workspaceDB,
			$dbLog,
			$writer,
			new MigrationConfig( [] ),
			new WikisConfig( $workspaceDB )
		);
		$processor->execute();

		$currentPage = $this->findRowById( $workspaceDB->getPages(), 'page_id', 300 );
		$this->assertSame(
			'TEST:Sample_page',
			$currentPage['wiki_title'],
			'Expected the current page to keep the plain title instead of being ' .
			'suffixed against deleted pages that will never be exported.'
		);
	}

}
