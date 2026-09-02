<?php

namespace HalloWelt\MigrateConfluence\Database\DataReader;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

/**
 * Base class for domain-specific read-only facades around {@see WorkspaceDB}.
 *
 * Unlike writes, reads do not need to be funneled through a single writer process
 * via a pipe: SQLite allows any number of concurrent readers without locking, so
 * every worker can simply open its own WorkspaceDB connection and read directly.
 * There is therefore no pipe-based counterpart to this class (compare with
 * IDataWriter/AbstractDirectDataWriter/AbstractPipeDataWriter).
 */
abstract class AbstractDataReader {

	public function __construct(
		protected WorkspaceDB $db
	) {
	}
}
