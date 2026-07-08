<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Page;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Page::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Page(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			new MigrationConfig( [ 'include-history' => true ] )
		);

		$this->executeProcessorForClass( $processor, __DIR__ . '/page.xml', 'Page' );

		$pages = $this->workspaceDB->getPages();
		$this->assertCount( 1, $pages, 'Expected exactly one page row.' );

		$page = $pages[0];
		$this->assertSame( 30, $page['page_id'], 'Unexpected page_id value.' );
		$this->assertSame( 10, $page['space_id'], 'Unexpected space_id value.' );
		$this->assertSame( 'Sample Page', $page['confluence_title'], 'Unexpected confluence_title value.' );
		$this->assertSame( '', $page['wiki_title'], 'Unexpected wiki_title value.' );
		$this->assertSame( 11, $page['parent_page_id'], 'Unexpected parent_page_id value.' );
		$this->assertSame( 'current', $page['content_status'], 'Unexpected content_status value.' );
		$this->assertSame( '3', $page['version'], 'Unexpected version value.' );
		$this->assertSame( -1, $page['original_version_id'], 'Unexpected original_version_id value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-07-08 13:14:15.000' ) ),
			$page['revision_timestamp'],
			'Unexpected revision_timestamp value.'
		);
		$this->assertSame( '["29"]', $page['historical_ids'], 'Unexpected historical_ids value.' );
		$this->assertSame( 'userkey1', $page['last_modifier'], 'Unexpected last_modifier value.' );
		$this->assertSame( '["300"]', $page['body_content_ids'], 'Unexpected body_content_ids value.' );

		$properties = json_decode( $page['properties'], true );
		$this->assertSame( 'Sample Page', $properties['title'], 'Unexpected properties.title value.' );
		$this->assertSame( 'current', $properties['contentStatus'], 'Unexpected properties.contentStatus value.' );
		$this->assertSame( '10', $properties['space'], 'Unexpected properties.space value.' );
		$this->assertSame( '11', $properties['parent'], 'Unexpected properties.parent value.' );

		$collection = json_decode( $page['collection'], true );
		$this->assertSame( [ '300' ], $collection['bodyContents'], 'Unexpected collection.bodyContents value.' );
		$this->assertSame( [ '29' ], $collection['historicalVersions'],
			'Unexpected collection.historicalVersions value.'
		);
	}
}
