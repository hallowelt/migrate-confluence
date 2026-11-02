<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

interface IDataWriter {

	/**
	 * @param string $timestamp ISO 8601
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 *
	 * @return void
	 */
	public function addLogEntry(
		string $timestamp, string $type, string $step, string $caller, string $text
	): void;

	/**
	 * @return void
	 */
	public function beginTransaction(): void;

	/**
	 * @return void
	 */
	public function commitTransaction(): void;

	/**
	 * @return void
	 */
	public function rollbackTransaction(): void;
}
