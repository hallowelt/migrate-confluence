<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 */
class ExtractPagesMetaData extends ProcessorBase {

	/** @var DBLog */
	protected DBLog $dbLog;

	/** @var MigrationConfig */
	protected MigrationConfig $migrationConfig;

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, DBLog $dbLog, MigrationConfig $migrationConfig ) {
		$this->workspaceDB = $workspaceDB;
		$this->dbLog = $dbLog;
		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$categories = $this->migrationConfig->getCategories();

			if ( isset( $page['page_id'] ) && isset( $page['content_status'] )
				&& strtolower( (string)$page['content_status'] ) === 'current'
			) {
				if ( !isset( $page['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $page['collection']['labellings'];
				foreach ( $labellings as $labellingId ) {
					$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
					if ( $labelling === null || !isset( $labelling['label_id'] ) ) {
						continue;
					}
					$labelId = (int)$labelling['label_id'];
					$label = $this->workspaceDB->getLabelById( $labelId );
					if ( $label === null || !isset( $label['name'] ) ) {
						continue;
					}

					$categories[] = $label['name'];
				}

				$categories = array_unique( $categories );

				$this->workspaceDB->addPageMeta(
					(int)$page['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add page meta for page {$page['wiki_title']}"
				);
			}
		}
	}

}
