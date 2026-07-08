<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBLog;

trait PreprocessorTestHelper {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	private function createDBLog( WorkspaceDB $workspaceDB ): DBLog {
		return new DBLog( $workspaceDB );
	}

	private function findRowById( array $rows, string $idField, int|string $idValue ): ?array {
		foreach ( $rows as $row ) {
			if ( isset( $row[$idField] ) && (string)$row[$idField] === (string)$idValue ) {
				return $row;
			}
		}

		return null;
	}
}
