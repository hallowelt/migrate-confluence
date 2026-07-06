<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class PageTemplates extends ProcessorBase {

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
		$templateId = null;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$templateId = (int)$this->getCDATAValue();
				} else {
					$templateId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		if ( $templateId === null ) {
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page Template has no ID."
			);

			return;
		}

		$name = $properties['name'] ?? '';
		if ( $name === '' ) {
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page Template with ID $templateId has no title."
			);

			return;
		}

		$spaceId = isset( $properties['space'] ) ? (int)$properties['space'] : null;
		if ( $spaceId === null ) {
			// History version of a template is missing space, original_version_id
			// or history_version_ids.
			// It is not possible to link a template revision to its original template_id
			// or other revisions, especially if more than.
			// Therefore we skip the template if space is missing for now.
			return;
		}

		$content = $properties['content'] ?? '';

		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';
		$contentStatus = $properties['contentStatus'] ?? 'current';

		$this->workspaceDB->addPageTemplateContents( $templateId, $content );

		unset( $properties['content'] );

		$status = $this->workspaceDB->addPageTemplate(
			$templateId,
			$name,
			$spaceId,
			'',
			'',
			$revisionTimestamp,
			$version,
			$properties,
			$collection,
			$contentStatus
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add page '$name' (ID: $templateId) to the database."
				. " This may indicate a problem with the page id. Maybe it does exist twice."
			);
		}

		$this->output->writeln( "Add page template '$name' (ID:$templateId)" );
	}
}
