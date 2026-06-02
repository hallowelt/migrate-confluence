<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Space id of historical versions can be -1 but we need the space id for converter
 * Use space id from original version and update history versions
 */
class UpdatePagesTableWithSpaceIdOfHistoryVersions extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$pageIdToSpaceIdMap = [];
		$pages = $this->workspaceDB->getPages();

		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'] ) || !array_key_exists( 'space_id', $page ) ) {
				continue;
			}

			if ( $page['space_id'] === null ) {
				continue;
			}

			$pageIdToSpaceIdMap[(int)$page['page_id']] = (int)$page['space_id'];
		}

		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'] )
				|| !array_key_exists( 'space_id', $page )
				|| !isset( $page['original_version_id'] )
			) {
				continue;
			}

			$originalVersionId = (int)$page['original_version_id'];
			if ( $originalVersionId === -1 ) {
				continue;
			}

			if ( $page['space_id'] !== null ) {
				continue;
			}

			$pageId = (int)$page['page_id'];

			if ( !isset( $pageIdToSpaceIdMap[$originalVersionId] ) ) {
				continue;
			}
			$originalSpaceId = (int)$pageIdToSpaceIdMap[$originalVersionId];

			$this->workspaceDB->updatePageSpaceId( $pageId, $originalSpaceId );
			$this->writeln(
				"Updated space_id for historical page ID $pageId with space_id: $originalSpaceId"
			);
		}
	}

}
