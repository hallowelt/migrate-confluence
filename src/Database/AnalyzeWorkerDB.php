<?php

namespace HalloWelt\MigrateConfluence\Database;

use HalloWelt\MigrateConfluence\Utility\PipeToDB;

/**
 * A proxy for WorkspaceDB used in parallel worker processes.
 *
 * Instead of writing to SQLite directly, it serialises every write call and
 * sends it through a pipe to the orchestrator process, which holds the single
 * authoritative DB connection.
 *
 * Read methods delegate to a read-only WorkspaceDB connection.
 * Transaction control is suppressed so that the orchestrator can manage
 * commit boundaries across all workers.
 */
class AnalyzeWorkerDB {

	/** @var PipeToDB */
	private PipeToDB $pipe;

	/** @var WorkspaceDB */
	private WorkspaceDB $readDb;

	/**
	 * @param PipeToDB $pipe
	 * @param WorkspaceDB $readDb Read-only connection for read operations
	 */
	public function __construct( PipeToDB $pipe, WorkspaceDB $readDb ) {
		$this->pipe = $pipe;
		$this->readDb = $readDb;
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array {
		return $this->readDb->getMapSpaceIdToPrefix();
	}

	/**
	 * No-op: the orchestrator manages transactions.
	 */
	public function beginTransaction(): void {
	}

	/**
	 * No-op: the orchestrator manages transactions.
	 */
	public function commitTransaction(): void {
	}

	/**
	 * Forward any write call to the orchestrator via the pipe.
	 *
	 * The orchestrator decodes the JSON line and calls the same method on its
	 * real WorkspaceDB instance.
	 *
	 * @param string $name   WorkspaceDB method name
	 * @param array  $args   Method arguments
	 * @return true  Callers that check boolean return values receive true.
	 */
	public function __call( string $name, array $args ): mixed {
		$this->pipe->send( $name, ...$args );
		return true;
	}
}
