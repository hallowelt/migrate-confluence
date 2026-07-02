<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class PageTemplates extends ProcessorBase {

	public function __construct(
		private IAnalyzeDataWriter $writer,
		private WorkspaceDB $workspaceDB
	) {
	}

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
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				'Page Template has no ID.'
			);

			return;
		}

		$name = $properties['name'] ?? '';
		if ( $name === '' ) {
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page Template with ID $templateId has no title."
			);

			return;
		}

		$spaceId = isset( $properties['space'] ) ? (int)$properties['space'] : null;
		if ( $spaceId === null ) {
			return;
		}

		$content = $properties['content'] ?? '';

		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';
		$contentStatus = $properties['contentStatus'] ?? 'current';

		$wikiTitle = '';
		try {
			$wikiTitle = $this->buildTemplateTitle( $name, $spaceId );
		} catch ( InvalidTitleException $e ) {
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"Page Template with ID $templateId has invalid title '$name': " . $e->getMessage()
			);

			$this->writer->addInvalidPageTemplateTitle(
				$templateId,
				$wikiTitle,
				"Page Template with ID $templateId has invalid title '$name': " . $e->getMessage()
			);

			return;
		}

		$this->writer->addPageTemplateContents( $templateId, $content );

		unset( $properties['content'] );

		$status = $this->writer->addPageTemplate(
			$templateId,
			$name,
			$spaceId,
			$wikiTitle,
			$revisionTimestamp,
			$version,
			$properties,
			$collection,
			$contentStatus
		);

		if ( !$status ) {
			$this->writer->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add page '$name' (ID: $templateId) to the database."
				. ' This may indicate a problem with the page id. Maybe it does exist twice.'
			);
		}

		$this->output->writeln( "Add page template '$name' (ID:$templateId)" );
	}

	private function buildTemplateTitle( string $name, ?int $spaceId ): string {
		$builder = new GenericTitleBuilder( $this->workspaceDB->getMapSpaceIdToPrefix() );
		$builder->setNamespace( GenericTitleBuilder::NS_TEMPLATE );

		$spaces = $this->workspaceDB->getMapSpaceIdToPrefix();
		if ( isset( $spaces[$spaceId] ) ) {
			$spacePrefix = $spaces[$spaceId];
			$spacePrefix = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) );
			$builder->appendTitleSegment( $spacePrefix );
		}

		$builder->appendTitleSegment( $name );
		return $builder->build();
	}
}
