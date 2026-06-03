<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use Symfony\Component\Console\Output\Output;

interface IExtractorProcessor {

	/**
	 * @return void
	 */
	public function execute(): void;

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void;
}
