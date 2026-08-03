<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

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

	public function beginTransaction(): void {
		$this->send( __FUNCTION__ );
	}

	public function commitTransaction(): void {
		$this->send( __FUNCTION__ );
	}

	public function rollbackTransaction(): void {
		$this->send( __FUNCTION__ );
	}
}
