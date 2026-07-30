<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

interface IDataWriter {

	/**
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 *
	 * @return void
	 */
	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void;
}
