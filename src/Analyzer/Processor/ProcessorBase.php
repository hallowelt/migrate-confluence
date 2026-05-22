<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use XMLReader;

abstract class ProcessorBase implements IAnalyzerProcessor {

	/** @var array */
	protected array $config = [];

	/** @var OutputInterface */
	protected OutputInterface $output;

	/** @var LoggerInterface */
	protected LoggerInterface $logger;

	/** @var XMLReader */
	protected XMLReader $xmlReader;

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
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
	 * @param XMLReader $xmlReader
	 * @return void
	 */
	public function execute( XMLReader $xmlReader ): void {
		$this->xmlReader = $xmlReader;
		$this->doExecute();
	}

	/**
	 * @return void
	 */
	protected function doExecute(): void {
	}

	/**
	 * @return string
	 */
	protected function processIdNode(): string {
		$id = '';
		if ( strtolower( $this->xmlReader->name ) === 'id' ) {
			$name = $this->xmlReader->getAttribute( 'name' );
			if ( $name === 'key' ) {
				$id = $this->getCDATAValue();
			} else {
				$id = $this->getTextValue();
			}
		}
		return $id;
	}

	/**
	 * @return string
	 */
	protected function getCDATAValue(): string {
		return $this->xmlReader->value;
	}

	/**
	 * @return string
	 */
	protected function getTextValue(): string {
		return $this->xmlReader->readString();
	}

	/**
	 * @param array $data
	 * @return mixed
	 */
	protected function processElementNode( array $data = [] ): mixed {
		if ( $this->xmlReader->isEmptyElement ) {
			$data = '';
			return $data;
		}

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->processIdNode() !== '' ) {
				$data = $this->processIdNode();
			} elseif ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
				$data = $this->getCDATAValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::TEXT ) {
				$data = $this->getTextValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::ELEMENT ) {
				$data = $this->processElementNode();
			}

			$this->xmlReader->next();
		}
		return $data;
	}

	/**
	 * @param array $properties
	 * @return array
	 */
	protected function processPropertyNodes( array $properties = [] ): array {
		$name = $this->xmlReader->getAttribute( 'name' );

		if ( $this->xmlReader->isEmptyElement ) {
			$properties[$name] = '';
			return $properties;
		}

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
				$properties[$name] = $this->getCDATAValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::TEXT ) {
				$properties[$name] = $this->getTextValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::ELEMENT ) {
				$properties[$name] = $this->processElementNode();
			}

			$this->xmlReader->next();
		}
		return $properties;
	}

	/**
	 * @param array $collection
	 * @param string $name
	 *
	 * @return array
	 */
	protected function processCollectionNodes( array $collection = [], string $name = '' ): array {
		$elementName = $this->xmlReader->getAttribute( 'name' );

		if ( $name !== '' && $elementName !== $name ) {
			return $collection;
		}

		if ( $this->xmlReader->isEmptyElement ) {
			$collection[$name] = [];
			return $collection;
		}

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name !== 'element' ) {
				$this->xmlReader->next();
				continue;
			}

			if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
				$collection[$elementName][] = $this->getCDATAValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::TEXT ) {
				$collection[$elementName][] = $this->getTextValue();
			} elseif ( $this->xmlReader->nodeType === XMLReader::ELEMENT ) {
				$collection[$elementName][] = $this->processElementNode();
			}

			$this->xmlReader->next();
		}
		return $collection;
	}

	/**
	 * @param string $lastModificationDate
	 * @return string
	 */
	protected function buildTimestamp( string $lastModificationDate ): string {
		$time = strtotime( $lastModificationDate );

		if ( $time === false ) {
			return '';
		}

		return date( 'YmdHis', $time );
	}
}
