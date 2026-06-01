<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\IExtractorProcessor;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IExtractorProcessor {

	/** @var Output|null */
	protected ?Output $output = null;

	/** @var WorkspaceDB */
	protected WorkspaceDB $workspaceDB;

	/** @var DBLog */
	protected DBLog $dbLog;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB, DBLog $dbLog ) {
		$this->workspaceDB = $workspaceDB;
		$this->dbLog = $dbLog;
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
