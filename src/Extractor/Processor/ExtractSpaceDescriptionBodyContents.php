<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 */
class ExtractSpaceDescriptionBodyContents extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param Workspace $workspace
	 * @param IExtractorDataWriter $writer
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		protected Workspace $workspace,
		IExtractorDataWriter $writer
	) {
		parent::__construct( $workspaceDB, $writer );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getCurrentSpaceDescriptions() as $spaceDescription ) {
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

		$bodyContentIds = [];
		foreach ( $currentContentIds as $currentContentId ) {
			$bodyContentIds = array_merge(
				$bodyContentIds,
				$this->workspaceDB->getBodyContentIdsForContentId( $currentContentId )
			);
		}

		$this->extractBodyContentIds( $bodyContentIds );
	}

	/**
	 * Extracts and saves raw content for an already resolved list of body content IDs.
	 *
	 * @param array $bodyContentIds
	 * @return void
	 */
	protected function extractBodyContentIds( array $bodyContentIds ): void {
		$bodyContentIds = array_values( array_unique( array_map( 'intval', $bodyContentIds ) ) );

		foreach ( $bodyContentIds as $bodyContentId ) {
			$body = $this->workspaceDB->getBodyContentBodyByBodyContentId( $bodyContentId );
			if ( $body === null ) {
				Log::to( 'db' )->warning(
					"No body found in body_content_bodies for body content ID $bodyContentId, skipping extraction."
				);
				continue;
			}

			$bodyContentHTML = $this->normalizeBodyContentHTML( $body );
			$targetFileName = $this->workspace->saveRawContent( (string)$bodyContentId, $bodyContentHTML );

			Log::to( 'db' )->info( "Extract body content to $targetFileName" );
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
