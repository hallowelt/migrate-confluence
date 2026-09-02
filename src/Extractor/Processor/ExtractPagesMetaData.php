<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataReader\ExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ExtractPagesMetaData extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 * @param ExtractorDataReader $dataReader
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
		IExtractorDataWriter $writer,
		ExtractorDataReader $dataReader,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog, $writer, $dataReader );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$configCategories = $this->migrationConfig->getCategories();

		foreach ( $this->dataReader->getCurrentPages() as $page ) {
			if ( !isset( $page['page_id'] ) || !isset( $page['original_version_id'] ) ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$originalVersionId = (int)$page['original_version_id'];
			$collection = json_decode( $page['collection'] ?? '{}', true ) ?? [];
			$labellings = $collection['labellings'] ?? [];

			if ( $originalVersionId !== -1 ) {
				continue;
			}

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
			$labelling = $this->dataReader->getLabellingById( (int)$labellingId );
			if ( !isset( $labelling['label_id'] ) ) {
				continue;
			}
			$labelId = (int)$labelling['label_id'];
			$label = $this->dataReader->getLabelById( $labelId );
			if ( $label === null || !isset( $label['name'] ) ) {
				continue;
			}

			$categories[] = $label['name'];
		}

		return array_unique( $categories );
	}

}
