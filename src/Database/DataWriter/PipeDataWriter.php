<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use HalloWelt\MigrateConfluence\Utility\PipeToDB;

class PipeDataWriter extends AbstractWriter {

	/**
	 * @param PipeToDB $pipe
	 */
	public function __construct( private readonly PipeToDB $pipe ) {
	}

	/**
	 * @param string $method
	 * @param array $args
	 *
	 * @return bool
	 */
	protected function dispatch( string $method, array $args ): bool {
		$this->pipe->send( $method, ...$args );

		return true;
	}
}
