<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface IConfluenceComposerProcessor {

	/**
	 * @param string $name
	 * @return void
	 */
	public function setSubDir( string $name ): void;

	/**
	 * @return void
	 */
	public function execute(): void;
}
