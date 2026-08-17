<?php

namespace HalloWelt\MigrateConfluence;

use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

interface IMigrationConfigAware {

	public function setMigrationConfig( MigrationConfig $migrationConfig ): void;
}
