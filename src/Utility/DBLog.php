<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class DBLog {

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( private WorkspaceDB $workspaceDB ){}

	/**
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 * @return void
	 */
	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$this->workspaceDB->addLogEntry( $type, $step, $caller, $text );
	}

	/**
	 * @param string $step
	 * @param string $type
	 * @return array
	 */
	public function getLogEntriesForStep( string $step, string $type = '' ): array {
		return $this->workspaceDB->getLogEntriesForStep( $step, $type );
	}
}