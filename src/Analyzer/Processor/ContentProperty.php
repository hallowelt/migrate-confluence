<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;


use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

/**
 * Preprocessor that reads ContentProperty objects linked to Comment objects
 * and collects the IDs of inline comments (identified by having an
 * "inline-comment" or "inline-marker-ref" property).
 */
class ContentProperty extends ProcessorBase {

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
	protected function doExecute(): void {
		$propertyId = [];
		$properties = [];
		$contentClass = '';

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$propertyId = (int)$this->getCDATAValue();
				} else {
					$propertyId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				// Capture the class attribute before processPropertyNodes consumes the element
				if ( $this->xmlReader->getAttribute( 'name' ) === 'content' ) {
					$contentClass = $this->xmlReader->getAttribute( 'class' );
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}
		$propName = $properties['name'] ?? null;
		if ( $propName !== 'inline-comment' && $propName !== 'inline-marker-ref' ) {
			return;
		}

		$status = $this->workspaceDB->addContentProperty(
			$propertyId,
			$propName,
			$contentClass,
			$properties
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add content property '$propName' to the database."
			);
		}

		$this->output->writeln( "Add content property '$propName'" );
	}
}
