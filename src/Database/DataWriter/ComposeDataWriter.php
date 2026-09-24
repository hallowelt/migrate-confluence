<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

/**
 * Parent-side accumulator for compose worker messages replayed via PipeReplay over the
 * existing DB pipe (fd 3). Compose has no DB writes to replay, so the IDataWriter methods
 * are no-ops; instead, each worker's per-namespace file extensions are collected here in
 * memory, so the finalize pass can aggregate them without writing/reading temporary files.
 */
class ComposeDataWriter implements IDataWriter {

	/** @var array<string,string[]> subDir (wikiName/namespace) => file extensions */
	private array $namespaceExtensions = [];

	public function addLogEntry( string $type, string $step, string $caller, string $text ): void {
	}

	public function beginTransaction(): void {
	}

	public function commitTransaction(): void {
	}

	public function rollbackTransaction(): void {
	}

	/**
	 * Replayed from a worker's PipeChannel message (see WikiBasedComposer). Not part of the
	 * IDataWriter contract — PipeReplay dispatches by method name dynamically, so this only
	 * needs to exist on this concrete class.
	 *
	 * @param string $subDir
	 * @param string[] $extensions
	 * @return void
	 */
	public function addNamespaceExtensions( string $subDir, array $extensions ): void {
		$this->namespaceExtensions[$subDir] = $extensions;
	}

	/**
	 * @return array<string,string[]> subDir (wikiName/namespace) => file extensions,
	 *   collected from all workers.
	 */
	public function getCollected(): array {
		return $this->namespaceExtensions;
	}
}
