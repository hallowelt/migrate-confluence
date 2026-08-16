<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IExtractorProcessor {

	/** @var Output|null */
	protected ?Output $output = null;

	/**
	 * @param IExtractorDataReader $reader
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 */
	public function __construct(
		protected IExtractorDataReader $reader,
		protected DBLog $dbLog,
		protected IExtractorDataWriter $writer
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
