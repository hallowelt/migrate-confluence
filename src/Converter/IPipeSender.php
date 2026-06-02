<?php

namespace HalloWelt\MigrateConfluence\Converter;

interface IPipeSender {

	/**
	 *
	 * @param resource|false $pipe
	 * @return void
	 */
	public function setPipe( $pipe ): void;
}
