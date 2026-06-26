<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\AnalyzeWorkerDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class Labelling extends ProcessorBase {

	/**
	 * @param WorkspaceDB|AnalyzeWorkerDB $workspaceDB
	 */
	public function __construct(
		private WorkspaceDB|AnalyzeWorkerDB $workspaceDB
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

		$status = $this->workspaceDB->addLabelling(
			$labellingId,
			$labelId,
			$properties
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add labelling (ID:$labellingId) to the database."
			);
		}

		$this->output->writeln( "Add labelling (ID:$labellingId)" );
	}
}
