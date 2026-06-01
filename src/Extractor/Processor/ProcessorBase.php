<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\IExtractorProcessor;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IExtractorProcessor {

	/** @var Output|null */
	protected ?Output $output = null;

	/** @var WorkspaceDB */
	protected WorkspaceDB $workspaceDB;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB ) {
		$this->workspaceDB = $workspaceDB;
	}

	/**
	 * @param string $message
	 * @param int $options
	 * @return void
	 */
	protected function writeln( string $message, int $options = Output::OUTPUT_NORMAL ): void {
		if ( $this->output instanceof Output ) {
			$this->output->writeln( $message, $options );
		}
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}
}
