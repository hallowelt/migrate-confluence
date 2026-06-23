<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface ISpaceDependentProcessor {

	/**
	 * @param int $spaceId
	 */
	public function setCurrentSpaceId( int $spaceId ): void;
}
