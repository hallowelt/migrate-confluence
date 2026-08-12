<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface ISpacesDependentProcessor {

	/**
	 * @param array $spaces
	 */
	public function setCurrentSpaces( array $spaces ): void;
}
