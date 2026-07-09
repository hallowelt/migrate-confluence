<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\PageTemplates;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\PageTemplates;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class PageTemplatesTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\PageTemplates::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();
		$this->workspaceDB->addSpace( 10, 'TEST', 'Test Space', 'TEST:', -1, -1 );

		$processor = new PageTemplates(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			$this->workspaceDB
		);
		$this->executeProcessorForClass( $processor, __DIR__ . '/page_template.xml', 'PageTemplate' );

		$pageTemplates = $this->workspaceDB->getPageTemplates();
		$this->assertCount( 1, $pageTemplates, 'Expected exactly one page template row.' );

		$pageTemplate = $pageTemplates[0];
		$this->assertSame( 70, $pageTemplate['template_id'], 'Unexpected template_id value.' );
		$this->assertSame( 10, $pageTemplate['space_id'], 'Unexpected space_id value.' );
		$this->assertSame( 'MyTemplate', $pageTemplate['confluence_title'], 'Unexpected confluence_title value.' );
		$this->assertSame( '', $pageTemplate['wiki_title'], 'Unexpected wiki_title value.' );
		$this->assertSame( 'current', $pageTemplate['content_status'], 'Unexpected content_status value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-07-08 18:19:20.000' ) ),
			$pageTemplate['revision_timestamp'],
			'Unexpected revision_timestamp value.'
		);
		$this->assertSame( '4', $pageTemplate['version'], 'Unexpected version value.' );

		$properties = json_decode( $pageTemplate['properties'], true );
		$this->assertSame( 'MyTemplate', $properties['name'], 'Unexpected properties.name value.' );
		$this->assertArrayNotHasKey( 'content', $properties, 'Did not expect content key in properties.' );
		$this->assertSame( '10', $properties['space'], 'Unexpected properties.space value.' );

		$this->assertSame( '[]', $pageTemplate['collection'], 'Unexpected collection value.' );

		$templateContents = $this->workspaceDB->getPageTemplateContents();
		$this->assertCount( 1, $templateContents, 'Expected exactly one page template content row.' );
		$this->assertSame( 70, $templateContents[0]['template_id'], 'Unexpected template content template_id value.' );
		$this->assertSame( '<p>Template Body</p>', $templateContents[0]['content'],
			'Unexpected template content value.'
		);
	}
}
