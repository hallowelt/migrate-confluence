<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ExtractPagesMetaData extends ProcessorBase {

	/**
	 * @param IExtractorDataReader $reader
	 * @param IExtractorDataWriter $writer
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		IExtractorDataReader $reader,
		IExtractorDataWriter $writer,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $reader, $writer );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$configCategories = $this->migrationConfig->getCategories();

		foreach ( $this->reader->getCurrentPages() as $page ) {
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

			$this->writer->addLogEntry(
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
			$labelling = $this->reader->getLabellingById( (int)$labellingId );
			if ( !isset( $labelling['label_id'] ) ) {
				continue;
			}
			$labelId = (int)$labelling['label_id'];
			$label = $this->reader->getLabelById( $labelId );
			if ( $label === null || !isset( $label['name'] ) ) {
				continue;
			}

			$categories[] = $label['name'];
		}

		return array_unique( $categories );
	}

}
