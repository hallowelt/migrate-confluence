<?php

namespace HalloWelt\MigrateConfluence;

interface IDestinationPathAware {

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void;
}
