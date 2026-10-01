<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Older Confluence exports don't carry a "space" property on the attachment
 * object itself, leaving attachments.space_id NULL. An attachment always
 * belongs to the space of its container page or blog post, so backfill the
 * missing value from there. This keeps attachments.space_id reliable for any
 * later step (composer namespace filtering, invalid-attachment logging, etc.)
 * instead of requiring every consumer to duplicate this fallback.
 */
class UpdateAttachmentsTableWithSpaceIdFallback extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if ( !isset( $attachment['attachment_id'] ) || !array_key_exists( 'space_id', $attachment ) ) {
				continue;
			}

			if ( $attachment['space_id'] !== null ) {
				continue;
			}

			$containerId = isset( $attachment['container_id'] ) ? (int)$attachment['container_id'] : -1;
			if ( $containerId <= 0 ) {
				continue;
			}

			$spaceId = $this->workspaceDB->getSpaceIdByContentId( $containerId );
			if ( $spaceId === null ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$this->writer->updateAttachmentSpaceId( $attachmentId, $spaceId );

			$this->writeln(
				"Updated space_id for attachment ID $attachmentId with space_id: $spaceId"
				. " (inherited from container ID $containerId)"
			);
		}
	}

}
