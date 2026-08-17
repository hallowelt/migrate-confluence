<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use HalloWelt\MediaWiki\Lib\Migration\Database\DataWriter\IDataWriter;

abstract class AbstractPipeDataWriter implements IDataWriter {

	public function __construct( protected PipeChannel $channel ) {
	}

	/** Every concrete domain method funnels through here. */
	protected function send( string $method, mixed ...$args ): void {
		$this->channel->send( [ $method, ...$args ] );
	}

	public function addLogEntry( string $type, string $step, string $caller, string $text ): void {
		$this->send( __FUNCTION__, $type, $step, $caller, $text );
	}

	/**
	 * Transaction control is not forwarded: all workers share one connection in the
	 * parent, so one worker's COMMIT/ROLLBACK would apply to every other worker's
	 * in-flight records.
	 */
	public function beginTransaction(): void {
	}

	public function commitTransaction(): void {
	}

	public function rollbackTransaction(): void {
	}
}
