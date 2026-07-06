<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use LogicException;

class DBLog {

	public function __construct( private WorkspaceDB|IAnalyzeDataWriter $dataTarget ) {
	}

	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$this->dataTarget->addLogEntry( $type, $step, $caller, $text );
	}

	public function getLogEntriesForStep( string $step, string $type = '' ): array {
		if ( !( $this->dataTarget instanceof WorkspaceDB ) ) {
			throw new LogicException( 'Reading DB log entries requires a WorkspaceDB instance.' );
		}
		return $this->dataTarget->getLogEntriesForStep( $step, $type );
	}
}
