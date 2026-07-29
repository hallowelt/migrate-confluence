<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class DirectDataWriter extends AbstractWriter {

	/**
	 * @param WorkspaceDB $db
	 */
	public function __construct( private readonly WorkspaceDB $db ) {
	}

	/**
	 * @param string $method
	 * @param array $args
	 *
	 * @return mixed
	 */
	protected function dispatch( string $method, array $args ): mixed {
		return $this->db->$method( ...$args );
	}
}
