<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::addExportProperties
 */
class ExportPropertiesTest extends TestCase {
	use ExportPropertiesQueryHelper;

	public function testStoresAllFields(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addExportProperties(
			'HR',
			'server',
			'7.19.18',
			'Fri Jul 03 09:54:18 CEST 2026',
			'',
			'_data_h_input_HH/entities.xml'
		);

		$rows = $this->queryExportProperties( $db );
		$this->assertCount( 1, $rows );

		$row = $rows[0];
		$this->assertSame( 'HR', $row['space_key'] );
		$this->assertSame( 'server', $row['source'] );
		$this->assertSame( '7.19.18', $row['confluence_version'] );
		$this->assertSame( 'Fri Jul 03 09:54:18 CEST 2026', $row['export_date'] );
		$this->assertSame( '', $row['timezone_id'] );
		$this->assertSame( '_data_h_input_HH/entities.xml', $row['entities_xml_path'] );
	}

	public function testCloudExportWithTimezone(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addExportProperties(
			'ZVA',
			'cloud',
			'1000.0.0-8e2084093f58',
			'Thu Mar 19 15:41:51 UTC 2026',
			'GMT',
			'_data_g_input/entities.xml'
		);

		$rows = $this->queryExportProperties( $db );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'cloud', $rows[0]['source'] );
		$this->assertSame( 'GMT', $rows[0]['timezone_id'] );
	}
}
