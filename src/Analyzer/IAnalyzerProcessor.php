<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use Symfony\Component\Console\Output\OutputInterface;
use XMLReader;

interface IAnalyzerProcessor {

	/**
	 * @param XMLReader $xmlReader
	 * @return void
	 */
	public function execute( XMLReader $xmlReader ): void;

	/**
	 * @param OutputInterface $output
	 */
	public function setOutput( OutputInterface $output ): void;
}
