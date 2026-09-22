<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

/**
 * No-op writer for WorkerPool::run() when children never send anything over the
 * DB pipe (fd 3). Used by the Compose command, which currently has no per-worker
 * DB writes to replay.
 */
class NullDataWriter implements IDataWriter {

	public function addLogEntry( string $type, string $step, string $caller, string $text ): void {
	}

	public function beginTransaction(): void {
	}

	public function commitTransaction(): void {
	}

	public function rollbackTransaction(): void {
	}
}
