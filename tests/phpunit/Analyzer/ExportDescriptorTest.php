<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer;

use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\ExportPropertiesQueryHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer::analyze
 */
class ExportDescriptorTest extends TestCase {
	use ExportPropertiesQueryHelper;

	private string $tempDir;

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-export-descriptor-test-' . uniqid();
		mkdir( $this->tempDir );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->tempDir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->tempDir );
	}

	public function testExportDescriptorIsWrittenToDb(): void {
		$minimalXml = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<hibernate-generic datetime="2020-01-01 00:00:00"></hibernate-generic>';
		file_put_contents( $this->tempDir . '/entities.xml', $minimalXml );
		file_put_contents(
			$this->tempDir . '/exportDescriptor.properties',
			"#Mon May 18 11:58:00 CEST 2026\n" .
			"source=server\n" .
			"createdByVersionNumber=6.15.9\n" .
			"spaceKey=HR\n"
		);

		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$analyzer = new ConfluenceAnalyzer(
			new AnalyzeDirectDataWriter( $workspaceDB ),
			$workspaceDB,
			new NullOutput(),
			new MigrationConfig( [] )
		);

		$analyzer->analyze( new SplFileInfo( $this->tempDir . '/entities.xml' ) );

		$rows = $this->queryExportProperties( $workspaceDB );
		$this->assertCount( 1, $rows );

		$row = $rows[0];
		$this->assertSame( 'HR', $row['space_key'] );
		$this->assertSame( 'server', $row['source'] );
		$this->assertSame( '6.15.9', $row['confluence_version'] );
		$this->assertSame( 'Mon May 18 11:58:00 CEST 2026', $row['export_date'] );
		$this->assertSame( '', $row['timezone_id'] );
		$this->assertStringEndsWith( '/entities.xml', $row['entities_xml_path'] );
		$this->assertStringNotContainsString( sys_get_temp_dir(), $row['entities_xml_path'] );
	}

	public function testMissingDescriptorFileIsSkippedGracefully(): void {
		$minimalXml = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<hibernate-generic datetime="2020-01-01 00:00:00"></hibernate-generic>';
		file_put_contents( $this->tempDir . '/entities.xml', $minimalXml );

		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$analyzer = new ConfluenceAnalyzer(
			new AnalyzeDirectDataWriter( $workspaceDB ),
			$workspaceDB,
			new NullOutput(),
			new MigrationConfig( [] )
		);

		$analyzer->analyze( new SplFileInfo( $this->tempDir . '/entities.xml' ) );

		$this->assertCount( 0, $this->queryExportProperties( $workspaceDB ) );
	}

	public function testNonEntitiesXmlFileIsIgnored(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$analyzer = new ConfluenceAnalyzer(
			new AnalyzeDirectDataWriter( $workspaceDB ),
			$workspaceDB,
			new NullOutput(),
			new MigrationConfig( [] )
		);

		$result = $analyzer->analyze( new SplFileInfo( $this->tempDir . '/something-else.xml' ) );

		$this->assertTrue( $result );
		$this->assertCount( 0, $this->queryExportProperties( $workspaceDB ) );
	}
}
