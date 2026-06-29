<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ExtractPagesMetaData extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		protected WorkspaceDB $workspaceDB,
		protected DBLog $dbLog,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$categories = $this->migrationConfig->getCategories();

		foreach ( $this->workspaceDB->getCurrentPages() as $page ) {
			if ( !isset( $page['page_id'] ) || !isset( $page['original_version_id'] ) ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$originalVersionId = (int)$page['original_version_id'];
			$labellings = $page['collection']['labellings'] ?? [];

			if ( $originalVersionId !== -1 ) {
				continue;
			}

			$categories = $this->getCategoryMeta( $labellings, $categories );

			if ( empty( $categories ) ) {
				continue;
			}

			$this->workspaceDB->addPageMeta(
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

}
