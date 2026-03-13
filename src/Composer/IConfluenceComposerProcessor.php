<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface IConfluenceComposerProcessor {

	/**
	 * @return void
	 */
	public function execute(): void;
}
