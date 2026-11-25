<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Reports deleted attachments whose history versions are not migrated, so that the skip is not silent.
 */
class LogSkippedDeletedAttachments extends ProcessorBase {

	/** @inheritDoc */
	public function execute(): void {
		$attachments = $this->workspaceDB->getAttachments();

		$notCurrent = [];
		foreach ( $attachments as $attachment ) {
			if ( ( $attachment['content_status'] ?? 'current' ) !== 'current' ) {
				$notCurrent[(int)$attachment['attachment_id']] = true;
			}
		}

		$historyCounts = [];
		foreach ( $attachments as $attachment ) {
			$headId = (int)( $attachment['original_version_id'] ?? -1 );
			if ( isset( $notCurrent[$headId] ) ) {
				$historyCounts[$headId] = ( $historyCounts[$headId] ?? 0 ) + 1;
			}
		}

		foreach ( $historyCounts as $headId => $count ) {
			Log::to( 'db' )->notice(
				"Attachment $headId is deleted; skipping its $count history versions."
			);
		}
	}
}
