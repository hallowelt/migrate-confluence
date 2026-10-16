<?php

namespace HalloWelt\MigrateConfluence\Extractor;

interface IExtractorProcessor {
	/**
	 * @return void
	 */
	public function execute(): void;
}
