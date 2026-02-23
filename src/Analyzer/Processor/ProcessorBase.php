<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ProcessorBase implements IAnalyzerProcessor {

	/** @var OutputInterface */
	protected $output;

	/** @var LoggerInterface */
	protected $logger;

	/** @var array */
	protected $data = [];

	/**
	 * @param OutputInterface $output
	 */
	public function setOutput( OutputInterface $output ): void {
		$this->output = $output;
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param array $data
	 * @return void
	 */
	public function setData( array $data ): void {
		$this->data = $data;
	}

	/**
	 * @inheritDoc
	 */
	public function getData( string $key ): array {
		if ( isset( $this->data[$key] ) ) {
			return $this->data[$key];
		}
		return [];
	}

	/**
	 * @return array
	 */
	public function getRequiredKeys(): array {
		return [];
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function execute( DOMDocument $dom ): void {
		$keys = array_merge(
			$this->getRequiredKeys(),
			$this->getKeys()
		);
		foreach ( $keys as $key ) {
			if ( !isset( $this->data[$key] ) ) {
				$this->data[$key] = [];
			}
		}
		$this->doExecute( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	protected function doExecute( DOMDocument $dom ): void {
	}
}
