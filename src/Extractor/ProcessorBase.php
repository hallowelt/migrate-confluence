<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataReader\ExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IExtractorProcessor {

	/** @var Output|null */
	protected ?Output $output = null;

	/**
	 * @param WorkspaceDB $workspaceDB Only kept for direct writes that bypass IExtractorDataWriter
	 *   (e.g. updatePageInterwikiTitle/updatePageSpaceId); use $dataReader for all reads.
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 * @param ExtractorDataReader $dataReader
	 */
	public function __construct(
		protected WorkspaceDB $workspaceDB,
		protected DBLog $dbLog,
		protected IExtractorDataWriter $writer,
		protected ExtractorDataReader $dataReader
	) {
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
