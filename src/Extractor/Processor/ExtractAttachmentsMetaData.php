<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractAttachmentsMetaData extends ExtractPagesMetaData {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			$categories = [];

			if ( isset( $attachment['page_id'] ) && isset( $attachment['original_version_id'] )
				&& (int)$attachment['original_version_id'] === -1
			) {
				if ( !isset( $attachment['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $attachment['collection']['labellings'];
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

				$this->workspaceDB->addAttachmentMeta(
					(int)$attachment['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add attachment meta for attachment {$attachment['wiki_title']}"
				);
			}
		}
	}

}
