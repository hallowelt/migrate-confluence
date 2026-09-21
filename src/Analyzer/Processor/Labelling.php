<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\SpaceFilter;
use XMLReader;

class Labelling extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @param SpaceFilter|null $spaceFilter
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer,
		private readonly ?SpaceFilter $spaceFilter = null
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$labellingId = -1;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$labellingId = (int)$this->getCDATAValue();
				} else {
					$labellingId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( !isset( $properties['label'] ) || $properties['label'] === '' ) {
			return;
		}

		if ( $this->spaceFilter !== null && !$this->spaceFilter->isLabellingAllowed( $labellingId ) ) {
			return;
		}

		$labelId = (int)$properties['label'];

		$status = $this->writer->addLabelling(
			$labellingId,
			$labelId,
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
				"Labelling ID $labellingId already exists in the database."
					. " Source directory: '$xmlDir', file: '$xmlFilename'."
			);
		}

		$this->output->writeln( "Add labelling (ID:$labellingId)" );
	}
}
