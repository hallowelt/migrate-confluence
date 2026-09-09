<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface ISubDirAware {

	/**
	 * @param string $name
	 * @return void
	 */
	public function setSubDir( string $name ): void;
}
