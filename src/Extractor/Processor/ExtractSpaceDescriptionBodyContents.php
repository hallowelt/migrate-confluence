<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataReader\ExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;

/**
 */
class ExtractSpaceDescriptionBodyContents extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param Workspace $workspace
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 * @param ExtractorDataReader $dataReader
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		protected Workspace $workspace,
		DBLog $dbLog,
		IExtractorDataWriter $writer,
		ExtractorDataReader $dataReader
	) {
		parent::__construct( $workspaceDB, $dbLog, $writer, $dataReader );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->dataReader->getCurrentSpaceDescriptions() as $spaceDescription ) {
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
			$bodyContentIds = $this->dataReader->getBodyContentIdsForContentId( $currentContentId );
			foreach ( $bodyContentIds as $bodyContentId ) {
				$body = $this->dataReader->getBodyContentBodyByBodyContentId( $bodyContentId );
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
