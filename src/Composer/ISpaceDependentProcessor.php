<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface ISpaceDependentProcessor {

	/**
	 * @param int[] $spaceIds
	 */
	public function setCurrentSpaceIds( array $spaceIds ): void;
}
