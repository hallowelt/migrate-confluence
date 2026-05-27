<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use XMLReader;

class PageTemplates extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB,
		private MigrationConfig $migrationConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$templateId = null;
		$properties = [];

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
			}
			$this->xmlReader->next();
		}

		if ( $templateId === null ) {
			return;
		}

		$name = $properties['name'] ?? '';
		if ( $name === '' ) {
			return;
		}

		$spaceId = isset( $properties['space'] ) ? (int)$properties['space'] : null;
		$content = $properties['content'] ?? '';

		$this->workspaceDB->addPageTemplate( $templateId, $name, $spaceId, $content );

		// Register the template as a page so it flows through the regular conversion pipeline.
		// Use the template ID as both "page ID" and "body content ID".
		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';

		$this->workspaceDB->addPage(
			$templateId,
			$spaceId ?? -1,
			$name,
			$this->buildTemplateTitle( $name, $spaceId ),
			$revisionTimestamp,
			'current',
			$version,
			-1,
			-1,
			[ $templateId ],
			[],
			$properties,
			[]
		);

		$this->workspaceDB->addBodyContent( $templateId, $templateId, 'PageTemplate', [] );

		$this->output->writeln( "Found page template '$name' (ID:$templateId)" );
	}

	/**
	 * Build the wiki title for the template page upfront.
	 * This avoids relying on updatePageTableWithWikiTitle() which doesn't handle templates.
	 *
	 * @param string $name
	 * @param int|null $spaceId
	 *
	 * @return string
	 */
	private function buildTemplateTitle( string $name, ?int $spaceId ): string {
		$templateNamespace= "Template";

		if ( !$spaceId ) {
			return "$templateNamespace:$name";
		}

		$spaces = $this->workspaceDB->getMapSpaceIdToPrefix();

		if ( !isset( $spaces[$spaceId] ) ) {
			return "$templateNamespace:$name";
		}

		$spacePrefix = $spaces[$spaceId];
		// Remove colon from space prefix
		$spacePrefix = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) );

		return "$templateNamespace:$spacePrefix/$name";
	}
}
