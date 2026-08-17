<?php

namespace HalloWelt\MigrateConfluence;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;

interface IWorkspaceAware {

	/**
	 * @param Workspace $workspace
	 * @return void
	 */
	public function setWorkspace( Workspace $workspace ): void;
}
