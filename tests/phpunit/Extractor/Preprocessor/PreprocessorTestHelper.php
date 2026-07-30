<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

trait PreprocessorTestHelper {

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	private function createDataWriter( WorkspaceDB $workspaceDB ): IExtractorDataWriter {
		return new ExtractorDirectDataWriter( $workspaceDB );
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
