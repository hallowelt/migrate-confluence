<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface IAnalyzerProcessor {

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	public function execute( DOMDocument $dom ): void;

	/**
	 * @return array
	 */
	public function getRequiredKeys(): array;

	/**
	 * @return array
	 */
	public function getKeys(): array;

	/**
	 * @param string $key
	 * @return array
	 */
	public function getData( string $key ): array;

	/**
	 * @param array $data
	 * @return void
	 */
	public function setData( array $data ): void;

	/**
	 * @param OutputInterface $output
	 */
	public function setOutput( OutputInterface $output ): void;

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void;
}