<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

class Label extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$labelId = -1;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$labelId = (int)$this->getCDATAValue();
				} else {
					$labelId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if (
			!isset( $properties['name'] ) ||
			!isset( $properties['namespace'] ) ||
			$properties['namespace'] !== 'global'
		) {
			// There may be `my` or `team` also
			$this->logger->warning( 'Missing label property name or namespace' );
			return;
		}

		$status = $this->writer->addLabel(
			$labelId,
			$properties['name'],
			$properties['namespace'],
			$properties
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			$xmlDir = dirname( $xmlFile );
			$xmlFilename = basename( $xmlFile );
			$this->writer->addLogEntry(
				'serious-error',
				'analyze',
				__CLASS__,
				"Label ID $labelId already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}

		$this->output->writeln( "Add label (ID:$labelId)" );
	}
}
