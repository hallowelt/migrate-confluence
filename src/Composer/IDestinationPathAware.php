<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface IDestinationPathAware {

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void;
}
