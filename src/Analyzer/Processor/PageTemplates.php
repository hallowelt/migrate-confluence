<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
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
		$content = $properties['content'] ?? '';

		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';

		try {
			$wikiTitle = $this->buildTemplateTitle( $name, $spaceId );
		} catch ( InvalidTitleException $e ) {
			$this->workspaceDB->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page Template with ID $templateId has invalid title '$name': " . $e->getMessage()
			);

			return;
		}

		$this->workspaceDB->addPageTemplateContents( $templateId, $content );

		$status = $this->workspaceDB->addPageTemplate(
			$templateId,
			$name,
			$spaceId,
			$wikiTitle,
			$revisionTimestamp,
			$version,
			'current',
			$properties
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
	}

	/**
	 * Build the wiki title for the template page upfront.
	 * This avoids relying on updatePageTableWithWikiTitle() which doesn't handle templates.
	 *
	 * @param string $name
	 * @param int|null $spaceId
	 *
	 * @return string
	 * @throws InvalidTitleException
	 */
	private function buildTemplateTitle( string $name, ?int $spaceId ): string {
		$builder = new GenericTitleBuilder( $this->workspaceDB->getMapSpaceIdToPrefix() );
		$builder->setNamespace( GenericTitleBuilder::NS_TEMPLATE );

		$spaces = $this->workspaceDB->getMapSpaceIdToPrefix();
		if ( isset( $spaces[$spaceId] ) ) {
			$spacePrefix = $spaces[$spaceId];
			// Remove colon from space prefix
			$spacePrefix = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) );
			$builder->appendTitleSegment( $spacePrefix );
		}

		$builder->appendTitleSegment( $name );
		return $builder->build();
	}
}
