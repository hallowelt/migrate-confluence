<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use HalloWelt\MediaWiki\Lib\Migration\Logging\LogEntryWriter;

/**
 * Log sink for worker processes: forwards entries over the DB pipe (fd 3) so that the
 * host process writes them to the workspace DB. Register it with `Log::setDatabaseHandler()`.
 */
class PipeLogEntryWriter implements LogEntryWriter {

	public function __construct( private PipeChannel $channel ) {
	}

	/** @inheritDoc */
	public function addLogEntry(
		string $timestamp, string $type, string $step, string $caller, string $text
	): void {
		$this->channel->send( [ 'addLogEntry', $timestamp, $type, $step, $caller, $text ] );
	}
}
