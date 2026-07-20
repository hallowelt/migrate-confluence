<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use ReflectionClass;

trait ExportPropertiesQueryHelper {

	private function queryExportProperties( WorkspaceDB $db ): array {
		$sqliteDb = ( new ReflectionClass( WorkspaceDB::class ) )
			->getProperty( 'db' )
			->getValue( $db );
		$result = $sqliteDb->query( 'SELECT * FROM export_properties' );
		$rows = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$rows[] = $row;
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}
		return $rows;
	}
}
