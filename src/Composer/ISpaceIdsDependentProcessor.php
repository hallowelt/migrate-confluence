<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface ISpaceIdsDependentProcessor {

	/**
	 * @param int[] $spaceIds
	 */
	public function setCurrentSpaceIds( array $spaceIds ): void;
}
