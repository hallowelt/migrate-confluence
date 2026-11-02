<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;

abstract class ProcessorBase implements IExtractorProcessor {
	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param IExtractorDataWriter $writer
	 */
	public function __construct(
		protected WorkspaceDB $workspaceDB,
		protected IExtractorDataWriter $writer
	) {
	}
}
