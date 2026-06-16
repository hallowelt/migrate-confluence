<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

class UpdatePagesTableWithSpaceIdOfHistoryVersions extends UpdateTableWithSpaceIdOfHistoryVersionsBase {

	/** @inheritDoc */
	protected function getRows(): array {
		return $this->workspaceDB->getPages();
	}

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'page';
	}

	/** @inheritDoc */
	protected function updateSpaceId( int $pageId, int $spaceId ): void {
		$this->workspaceDB->updatePageSpaceId( $pageId, $spaceId );
	}
}
