<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;

/**
 */
class ExtractSpaceDescriptionBodyContents extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param Workspace $workspace
	 * @param DBLog $dbLog
	 */
	public function __construct(
		protected WorkspaceDB $workspaceDB,
		protected Workspace $workspace,
		protected DBLog $dbLog
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getSpaceDescriptions() as $spaceDescription ) {
			if ( isset( $spaceDescription['space_description_id'] ) ) {
				$currentContentIds[] = (int)$spaceDescription['space_description_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @param array $currentContentIds
	 * @return void
	 */
	protected function doExtractBodyContent( array $currentContentIds ): void {
		$currentContentIds = array_values( array_unique( $currentContentIds ) );

		if ( $currentContentIds === [] ) {
			return;
		}

		foreach ( $currentContentIds as $currentContentId ) {
			$bodyContentIds = $this->workspaceDB->getBodyContentIdsForContentId( $currentContentId );
			foreach ( $bodyContentIds as $bodyContentId ) {
				$body = $this->workspaceDB->getBodyContentBodyByBodyContentId( $bodyContentId );
				if ( $body === null ) {
					continue;
				}

				$bodyContentHTML = $this->normalizeBodyContentHTML( $body );
				$targetFileName = $this->workspace->saveRawContent( (string)$bodyContentId, $bodyContentHTML );

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Extract body content to $targetFileName"
				);
			}
		}
	}

		/**
		 * @param string $rawValue
		 * @return string
		 */
	protected function normalizeBodyContentHTML( string $rawValue ): string {
		// For a strange reason the CDATA blocks are not closed properly...
		$fixedValue = str_replace( ']] >', ']]>', $rawValue );
		return '<html><body>' . $fixedValue . '</body></html>';
	}

}
