<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

trait PreprocessorTestHelper {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
		parent::tearDown();
	}

	private function createWorkspaceDB(): WorkspaceDB {
		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();
		Log::setDatabaseHandler( $workspaceDB );
		Log::setStep( 'extract' );
		return $workspaceDB;
	}

	private function createWriter( WorkspaceDB $workspaceDB ): IExtractorDataWriter {
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
