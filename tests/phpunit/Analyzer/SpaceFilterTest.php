<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer;

use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer::analyze
 * @covers \HalloWelt\MigrateConfluence\Analyzer\SpaceFilter
 * @covers \HalloWelt\MigrateConfluence\Analyzer\SpaceFilterPrescanProcessor
 */
class SpaceFilterTest extends TestCase {

	private string $tempDir;

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-space-filter-test-' . uniqid();
		mkdir( $this->tempDir );
		copy( __DIR__ . '/SpaceFilter/entities.xml', $this->tempDir . '/entities.xml' );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->tempDir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->tempDir );
	}

	private function writeDescriptor( string $spaceKey ): void {
		file_put_contents(
			$this->tempDir . '/exportDescriptor.properties',
			"#Mon May 18 11:58:00 CEST 2026\n" .
			"source=server\n" .
			"createdByVersionNumber=6.15.9\n" .
			"spaceKey=$spaceKey\n"
		);
	}

	private function analyze( bool $filterForeignSpaceData ): \HalloWelt\MigrateConfluence\Database\WorkspaceDB {
		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$analyzer = new ConfluenceAnalyzer(
			new AnalyzerDirectDataWriter( $workspaceDB ),
			new NullOutput(),
			new MigrationConfig( [ 'filter-foreign-space-data' => $filterForeignSpaceData ] ),
			new WikisConfig( $workspaceDB )
		);

		$analyzer->analyze( new SplFileInfo( $this->tempDir . '/entities.xml' ) );

		return $workspaceDB;
	}

	public function testForeignSpaceDataIsDroppedWhenEnabled(): void {
		$this->writeDescriptor( 'HR' );
		$workspaceDB = $this->analyze( true );

		$spaceIds = array_column( $workspaceDB->getSpaces(), 'space_id' );
		$this->assertSame( [ 1 ], $spaceIds, 'Only the HR space should remain.' );

		$pageIds = array_column( $workspaceDB->getPages(), 'page_id' );
		$this->assertSame( [ 100 ], $pageIds, 'Only the HR page should remain.' );

		$attachmentIds = array_column( $workspaceDB->getAttachments(), 'attachment_id' );
		$this->assertSame( [ 300 ], $attachmentIds, 'Only the HR attachment should remain.' );

		$bodyContentIds = array_column( $workspaceDB->getBodyContents(), 'body_content_id' );
		$this->assertSame( [ 8001 ], $bodyContentIds, 'Only the HR body content should remain.' );

		$commentIds = array_column( $workspaceDB->getComments(), 'comment_id' );
		$this->assertSame( [ 9001 ], $commentIds, 'Only the HR comment should remain.' );

		$spaceDescriptionIds = array_column( $workspaceDB->getSpaceDescriptions(), 'space_description_id' );
		$this->assertSame( [ 501 ], $spaceDescriptionIds, 'Only the HR space description should remain.' );

		$labellingIds = array_column( $workspaceDB->getLabellings(), 'labelling_id' );
		$this->assertSame( [ 7001 ], $labellingIds, 'Only the HR labelling should remain.' );

		$filtered = $workspaceDB->getFilteredObjects();
		$filteredTypes = array_count_values( array_column( $filtered, 'entity_type' ) );
		ksort( $filteredTypes );
		$this->assertSame(
			[
				'Attachment' => 1,
				'BodyContent' => 1,
				'Comment' => 1,
				'Labelling' => 1,
				'Page' => 1,
				'Space' => 1,
				'SpaceDescription' => 1,
			],
			$filteredTypes,
			'Every foreign object type should be recorded exactly once in filtered_objects.'
		);
	}

	public function testForeignSpaceDataIsKeptWhenDisabled(): void {
		$this->writeDescriptor( 'HR' );
		$workspaceDB = $this->analyze( false );

		$spaceIds = array_column( $workspaceDB->getSpaces(), 'space_id' );
		sort( $spaceIds );
		$this->assertSame( [ 1, 2 ], $spaceIds, 'Both spaces are kept when the filter is disabled (default).' );

		$this->assertCount( 0, $workspaceDB->getFilteredObjects() );
	}

	public function testNoMatchingSpaceKeepsEverythingAndLogsSeriousError(): void {
		$this->writeDescriptor( 'DOES-NOT-EXIST' );
		$workspaceDB = $this->analyze( true );

		$spaceIds = array_column( $workspaceDB->getSpaces(), 'space_id' );
		sort( $spaceIds );
		$this->assertSame(
			[ 1, 2 ],
			$spaceIds,
			'When no Space matches the expected key, filtering is skipped rather than dropping everything.'
		);

		$errors = $workspaceDB->getLogEntriesForStep( 'analyze', 'serious-error' );
		$this->assertNotEmpty( $errors, 'A serious-error log entry should explain why filtering was skipped.' );
	}
}
