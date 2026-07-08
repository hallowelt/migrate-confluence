<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 */
class ExtractAttachmentsMetaData extends ExtractPagesMetaData {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getCurrentAttachments() as $attachment ) {
			if ( !isset( $attachment['attachment_id'] ) || !isset( $attachment['original_version_id'] ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$originalVersionId = (int)$attachment['original_version_id'];
			$labellings = $attachment['collection']['labellings'] ?? [];

			if ( $originalVersionId !== -1 ) {
				continue;
			}

			$categories = $this->getCategoryMeta( $labellings );

			if ( empty( $categories ) ) {
				continue;
			}

			$this->workspaceDB->addAttachmentMeta(
				$attachmentId,
				[
					'categories' => $categories
				]
			);

			$this->dbLog->addLogEntry(
				'info',
				'extract',
				__METHOD__,
				"Add attachment meta for attachment {$attachment['wiki_title']}"
			);
		}
	}

}
