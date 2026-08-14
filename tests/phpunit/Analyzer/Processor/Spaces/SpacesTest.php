<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Spaces;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use PHPUnit\Framework\TestCase;

class SpacesTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();
		$this->workspaceDB->addWikisConfig( 'TEST', 'test-wiki', 'TEST', '' );

		$processor = new Spaces(
			new AnalyzerDirectDataWriter( $this->workspaceDB ),
			new WikisConfig( $this->workspaceDB )
		);

		$this->executeProcessorForClass( $processor, __DIR__ . '/spaces.xml', 'Space' );

		$spaces = $this->workspaceDB->getSpaces();
		$this->assertCount( 1, $spaces, 'Expected exactly one space row.' );

		$space = $spaces[0];
		$this->assertSame( 10, $space['space_id'], 'Unexpected space_id value.' );
		$this->assertSame( 'TEST', $space['space_key'], 'Unexpected space_key value.' );
		$this->assertSame( 'Test Space', $space['space_name'], 'Unexpected space_name value.' );
		$this->assertSame( 'TEST', $space['namespace_prefix'], 'Unexpected namespace_prefix value.' );
		$this->assertSame( 100, $space['homepage_id'], 'Unexpected homepage_id value.' );
		$this->assertSame( 101, $space['description_id'], 'Unexpected description_id value.' );
	}
}
