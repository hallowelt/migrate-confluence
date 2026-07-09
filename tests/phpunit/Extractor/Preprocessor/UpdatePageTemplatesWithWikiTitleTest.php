<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageTemplatesWithWikiTitle;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class UpdatePageTemplatesWithWikiTitleTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageTemplatesWithWikiTitle::execute
	 */
	public function testBuildsWikiTitleForPageTemplate(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPageTemplate(
			700,
			'Sample template',
			42,
			'',
			'',
			'1',
			[],
			[],
			'current'
		);

		$processor = new UpdatePageTemplatesWithWikiTitle( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$pageTemplate = $this->findRowById( $workspaceDB->getPageTemplates(), 'template_id', 700 );
		$this->assertNotNull( $pageTemplate, 'Expected page template row to exist.' );
		$this->assertNotSame( '', $pageTemplate['wiki_title'], 'Expected wiki_title to be generated.' );
		$this->assertStringStartsWith(
			'Template:TEST/',
			$pageTemplate['wiki_title'],
			'Expected generated page template wiki_title to use Template:TEST/ namespace prefix.'
		);
		$this->assertEquals(
			'Template:TEST/Sample_template',
			$pageTemplate['wiki_title'],
			'Expected generated page template wiki_title to be Template:TEST/Sample_template.'
		);
		$this->assertSame(
			[],
			$workspaceDB->getInvalidPageTemplates(),
			'Did not expect invalid page template titles.'
		);
	}
}
