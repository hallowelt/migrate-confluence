<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\SpaceDescription;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class SpaceDescriptionTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new SpaceDescription(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			new MigrationConfig( [ 'include-history' => true ] )
		);

		$this->executeProcessorForClass(
			$processor,
			__DIR__ . '/space_description.xml',
			'SpaceDescription'
		);

		$spaceDescriptions = $this->workspaceDB->getSpaceDescriptions();
		$this->assertCount( 1, $spaceDescriptions, 'Expected exactly one space description row.' );

		$spaceDescription = $spaceDescriptions[0];
		$this->assertSame( 20, $spaceDescription['space_description_id'], 'Unexpected space_description_id value.' );
		$this->assertSame( 'current', $spaceDescription['content_status'], 'Unexpected content_status value.' );
		$this->assertSame( '2', $spaceDescription['version'], 'Unexpected version value.' );
		$this->assertSame( 19, $spaceDescription['original_version_id'], 'Unexpected original_version_id value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-07-08 11:12:13.000' ) ),
			$spaceDescription['revision_timestamp'],
			'Unexpected revision_timestamp value.'
		);
		$this->assertSame( '["200"]', $spaceDescription['body_content_ids'], 'Unexpected body_content_ids value.' );
		$this->assertSame( '["300"]', $spaceDescription['labelling_ids'], 'Unexpected labelling_ids value.' );

		$properties = json_decode( $spaceDescription['properties'], true );
		$this->assertSame( '2', $properties['version'], 'Unexpected properties.version value.' );
		$this->assertSame( 'current', $properties['contentStatus'], 'Unexpected properties.contentStatus value.' );
		$this->assertSame( '19', $properties['originalVersion'], 'Unexpected properties.originalVersion value.' );

		$collection = json_decode( $spaceDescription['collection'], true );
		$this->assertSame( [ '200' ], $collection['bodyContents'], 'Unexpected collection.bodyContents value.' );
		$this->assertSame( [ '300' ], $collection['labellings'], 'Unexpected collection.labellings value.' );
	}
}
