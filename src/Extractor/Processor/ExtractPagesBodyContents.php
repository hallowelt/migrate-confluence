<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractPagesBodyContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getCurrentPages() as $page ) {
			if ( isset( $page['page_id'] ) ) {
				$currentContentIds[] = (int)$page['page_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

}
