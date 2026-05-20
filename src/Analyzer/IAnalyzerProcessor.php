<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\Output;
use XMLReader;

interface IAnalyzerProcessor {

	/**
	 * @param XMLReader $xmlReader
	 * @return void
	 */
	public function execute( XMLReader $xmlReader ): void;

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void;

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void;
}
