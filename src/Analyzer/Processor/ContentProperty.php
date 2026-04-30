<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

/**
 * Preprocessor that reads ContentProperty objects linked to Comment objects
 * and collects the IDs of inline comments (identified by having an
 * "inline-comment" or "inline-marker-ref" property).
 */
class ContentProperty extends ProcessorBase {

	/**
	 * @param ConfigDB $configDB
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private ConfigDB $configDB,
		private WorkspaceDB $workspaceDB
	) {}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(): void {
		$properties = [];
		$contentClass = null;

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'property' ) {
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

/*
		if ( $contentClass !== 'Comment' ) {
			return;
		}

		$commentId = isset( $properties['content'] ) ? (int)$properties['content'] : -1;
		if ( $commentId === -1 ) {
			return;
		}

		TODO: Maybe add a mapping for the $commentId
*/
		$this->workspaceDB->addContentProperty(
			$propName,
			$contentClass,
			$properties
		);

		$this->output->writeln( "Add content property '$propName'" );
	}
}
