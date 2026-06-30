<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;


use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class Label extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB
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

		if ( !isset( $properties['namespace'] ) || $properties['namespace'] !== 'global' ) {
			// There may be `my` or `team` also
			return;
		}

		$status = $this->workspaceDB->addLabel(
			$labelId,
			$properties['name'],
			$properties['namespace'],
			$properties
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add label (ID:$labelId) to the database."
			);
		}

		$this->output->writeln( "Add label (ID:$labelId)" );
	}
}
