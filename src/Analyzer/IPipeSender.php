<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

interface IPipeSender {

	/**
	 * @param resource|false $pipe
	 * @return void
	 */
	public function setPipe( $pipe ): void;
}
