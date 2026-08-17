<?php

namespace HalloWelt\MigrateConfluence;

use HalloWelt\MigrateConfluence\Utility\WikisConfig;

interface IWikisConfigAware {

	public function setWikisConfig( WikisConfig $wikisConfig ): void;
}
