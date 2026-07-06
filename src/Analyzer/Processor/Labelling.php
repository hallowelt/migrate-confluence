<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

class Labelling extends ProcessorBase {

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

		$labelId = (int)$properties['label'];

		$status = $this->writer->addLabelling(
			$labellingId,
			$labelId,
			$properties
		);

		if ( !$status ) {
			$this->writer->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add labelling (ID:$labellingId) to the database."
			);
		}

		$this->output->writeln( "Add labelling (ID:$labellingId)" );
	}
}
