<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\Output;
use XMLReader;

interface IExtractorProcessor {

	/**
	 * @param XMLReader $xmlReader
	 * @return void
	 */
	public function execute( XMLReader $xmlReader ): void;

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
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void;

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void;

	/**
	 * @param array $config
	 * @return void
	 */
	public function setConfig( array $config ): void;
}
