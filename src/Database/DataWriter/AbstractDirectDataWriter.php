<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

abstract class AbstractDirectDataWriter implements IDataWriter {

	public function __construct(
		protected WorkspaceDB $db
	) {
	}

	public function addLogEntry( string $type, string $step, string $caller, string $text ): void {
		$this->db->addLogEntry( $type, $step, $caller, $text );
	}

	public function beginTransaction(): void {
		$this->db->beginTransaction();
	}

	public function commitTransaction(): void {
		$this->db->commitTransaction();
	}
}
