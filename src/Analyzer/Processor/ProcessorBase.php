<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XMLReader;

abstract class ProcessorBase implements IAnalyzerProcessor {

	/** @var OutputInterface */
	protected $output;

	/** @var LoggerInterface */
	protected $logger;

	/** @var XMLReader */
	protected $xmlReader;

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
	 * @param XMLReader $xmlReader
	 * @return void
	 */
	public function execute( XMLReader $xmlReader ): void	{
		$keys = array_merge(
			$this->getRequiredKeys(),
			$this->getKeys()
		);
		foreach ( $keys as $key ) {
			if ( !isset( $this->data[$key] ) ) {
				$this->data[$key] = [];
			}
		}
		$this->xmlReader = $xmlReader;
		$this->doExecute();
	}

	/**
	 * @return void
	 */
	protected function doExecute(): void {
	}


	protected function processIdNode() {
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

	protected function getCDATAValue() {
		return $this->xmlReader->value;
	}

	protected function getTextValue() {
		return $this->xmlReader->readString();
	}

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

	protected function processCollectionNodes( array $collection = [], $name = '' ): array {
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

}
