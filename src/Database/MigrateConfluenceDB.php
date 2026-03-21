<?php

namespace HalloWelt\MigrateConfluence\Database;

use SQLite3;

abstract class MigrateConfluenceDB {

	/** @var Sqlite */
	protected $db;

	/**
	 * @param string $name
	 */
	public function __construct( string $name ) {
		$this->db = new SQLite3( $name );

		$this->createTables();
	}

	/**
	 * @return void
	 */
	protected function createTables(): void {
	}
}