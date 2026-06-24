<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IExtractorProcessor {

	/** @var Output|null */
	protected ?Output $output = null;

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 */
	public function __construct( protected WorkspaceDB $workspaceDB, protected DBLog $dbLog ) {
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
