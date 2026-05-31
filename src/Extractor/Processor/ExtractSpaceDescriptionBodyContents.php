<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBLog;

/**
 */
class ExtractSpaceDescriptionBodyContents extends ProcessorBase {

	/** @var Workspace */
	protected Workspace $workspace;

	/** @var DBLog */
	protected DBLog $dbLog;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, Workspace $workspace, DBLog $dbLog ) {
		$this->workspaceDB = $workspaceDB;
		$this->workspace = $workspace;
		$this->dbLog = $dbLog;
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getSpaceDescriptions() as $spaceDescription ) {
			if ( isset( $spaceDescription['space_description_id'] ) && isset( $spaceDescription['content_status'] )
				&& strtolower( (string)$spaceDescription['content_status'] ) === 'current'
			) {
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