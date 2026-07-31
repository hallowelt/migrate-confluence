<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\DataWriter\IDataWriter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use LogicException;

class DBLog {

	/**
	 * @param WorkspaceDB|IDataWriter $dataTarget
	 */
	public function __construct( private WorkspaceDB|IDataWriter $dataTarget ) {
	}

	/**
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 *
	 * @return void
	 */
	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$this->dataTarget->addLogEntry( $type, $step, $caller, $text );
	}

	/**
	 * @param string $step
	 * @param string $type
	 *
	 * @return array
	 */
	public function getLogEntriesForStep( string $step, string $type = '' ): array {
		if ( !( $this->dataTarget instanceof WorkspaceDB ) ) {
			throw new LogicException( 'Reading DB log entries requires a WorkspaceDB instance.' );
		}
		return $this->dataTarget->getLogEntriesForStep( $step, $type );
	}
}
