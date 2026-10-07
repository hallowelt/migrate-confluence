<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ExtractPagesMetaData extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
		IExtractorDataWriter $writer,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog, $writer );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$configCategories = $this->migrationConfig->getCategories();
		$labellingIdsByContent = $this->getLabellingIdsByContent();

		foreach ( $this->workspaceDB->getCurrentPages() as $page ) {
			if ( !isset( $page['page_id'] ) || !isset( $page['original_version_id'] ) ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$originalVersionId = (int)$page['original_version_id'];

			if ( $originalVersionId !== -1 ) {
				continue;
			}

			$collection = json_decode( $page['collection'] ?? '{}', true ) ?? [];
			$labellings = $collection['labellings'] ?? [];
			// Some exports only reference the page from the Labelling side (property "content")
			$labellings = array_values( array_unique( array_merge(
				$labellings,
				$labellingIdsByContent[$pageId] ?? []
			) ) );

			$categories = $this->getCategoryMeta( $labellings, $configCategories );

			if ( empty( $categories ) ) {
				continue;
			}

			$this->writer->addPageMeta(
				$pageId,
				[
					'categories' => $categories
				]
			);

			$this->dbLog->addLogEntry(
				'info',
				'extract',
				__METHOD__,
				"Add page category meta for page {$page['wiki_title']}"
			);
		}
	}

	/**
	 * @param array $labellings
	 * @param array $categories
	 *
	 * @return array
	 */
	protected function getCategoryMeta(
		array $labellings,
		array $categories = []
	): array {
		foreach ( $labellings as $labellingId ) {
			$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
			if ( !isset( $labelling['label_id'] ) ) {
				continue;
			}
			$labelId = (int)$labelling['label_id'];
			$label = $this->workspaceDB->getLabelById( $labelId );
			if ( $label === null || !isset( $label['name'] ) ) {
				continue;
			}

			$categories[] = $label['name'];
		}

		return array_unique( $categories );
	}

	/**
	 * Map content (page) id => labelling ids, based on the Labelling's "content" property
	 *
	 * @return array<int, array<int>>
	 */
	private function getLabellingIdsByContent(): array {
		$map = [];
		foreach ( $this->workspaceDB->getLabellings() as $row ) {
			$props = json_decode( $row['properties'] ?? '{}', true ) ?? [];
			$contentId = (int)( $props['content'] ?? 0 );
			if ( $contentId > 0 ) {
				$map[$contentId][] = (int)$row['labelling_id'];
			}
		}
		return $map;
	}
}
