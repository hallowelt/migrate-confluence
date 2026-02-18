<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class SpaceDescription extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-space-description-id-to-body-id-map'
		];
	}


	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$descriptionId = '';
		$properties = [];
		$collection = [];
		$bodyContents = [];
		$labellings = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$descriptionId = $this->getCDATAValue();
				} else {
					$descriptionId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
	
			$this->xmlReader->next();
		}

		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContents = $collection['bodyContents'];
		}
		if ( isset( $collection['labellings'] ) ) {
			$labellings = $collection['labellings'];
		}

		/*
		foreach ( $bodyContents as $bodyContent ) {
			//$this->buckets->addData( 'global-space-description-id-to-body-id-map', $descID, $id, false, true );
		}
		*/
		if ( !is_array( $this->data['global-space-description-id-to-body-id-map'][$descriptionId] ) ) {
			$this->data['global-space-description-id-to-body-id-map'][$descriptionId] = [];
		}
		$this->data['global-space-description-id-to-body-id-map'][$descriptionId][] = $bodyContents;
		$this->output->writeln( "\nAdd space description ($descriptionId)" );

		return;
	}
}
