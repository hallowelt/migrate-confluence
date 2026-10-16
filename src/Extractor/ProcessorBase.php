<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBLog;

abstract class ProcessorBase implements IExtractorProcessor {
	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 */
	public function __construct(
		protected WorkspaceDB $workspaceDB,
		protected DBLog $dbLog,
		protected IExtractorDataWriter $writer
	) {
	}
}
