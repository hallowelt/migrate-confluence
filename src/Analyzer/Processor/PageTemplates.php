<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class PageTemplates extends ProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [
			'global-space-id-to-prefix-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-page-template-id-to-name-map',
			'global-page-template-id-to-space-id-map',
			'global-page-template-id-to-content-map',
			'global-body-content-id-to-page-id-map',
			'global-page-id-to-space-id',
			'analyze-page-id-to-title-map',
			'analyze-title-revisions',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$templateId = null;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$templateId = (int)$this->getCDATAValue();
				} else {
					$templateId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
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

		$this->data['global-page-template-id-to-name-map'][$templateId] = $name;
		if ( $spaceId !== null ) {
			$this->data['global-page-template-id-to-space-id-map'][$templateId] = $spaceId;
		}
		if ( $content !== '' ) {
			$this->data['global-page-template-id-to-content-map'][$templateId] = $content;
		}

		// Register the template as a page so it flows through the regular conversion pipeline.
		// Use the template ID as both "page ID" and "body content ID".
		$spacePrefix = '';
		if ( $spaceId !== null && isset( $this->data['global-space-id-to-prefix-map'][$spaceId] ) ) {
			$spacePrefix = $this->data['global-space-id-to-prefix-map'][$spaceId];
		}
		$targetTitle = 'Template:' . $spacePrefix . $name;
		$this->data['analyze-page-id-to-title-map'][$templateId] = $targetTitle;
		$this->data['global-body-content-id-to-page-id-map'][$templateId] = $templateId;
		if ( $spaceId !== null ) {
			$this->data['global-page-id-to-space-id'][$templateId] = $spaceId;
		}

		$lastModificationDate = $properties['lastModificationDate'] ?? '';
		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );
		$version = $properties['version'] ?? '1';
		$revision = "$templateId@$version-$revisionTimestamp";
		$this->data['analyze-title-revisions'][$targetTitle][] = $revision;

		$this->output->writeln( "Found page template '$name' (ID:$templateId)" );
	}
}
