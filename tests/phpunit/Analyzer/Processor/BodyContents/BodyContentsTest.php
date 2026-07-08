<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BodyContents;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class BodyContentsTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new BodyContents( new AnalyzeDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/body_content_page.xml', 'BodyContent' );

		$bodyContents = $this->workspaceDB->getBodyContents();
		$this->assertCount( 1, $bodyContents, 'Expected exactly one body content row.' );

		$bodyContent = $bodyContents[0];
		$this->assertSame( 100, $bodyContent['body_content_id'], 'Unexpected body_content_id value.' );
		$this->assertSame( 200, $bodyContent['content_id'], 'Unexpected content_id value.' );
		$this->assertSame( 'Page', $bodyContent['class'], 'Unexpected class value.' );
		$this->assertSame( '{"content":"200"}', $bodyContent['properties'], 'Unexpected properties value.' );
	}
}
