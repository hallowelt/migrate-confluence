<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * Populate additional_attachments with attachments that are not part of page_attachments.
 *
 * The target filename is built from space prefix and original filename only.
 */
class PopulateAdditionalAttachmentsTable extends ProcessorBase {

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, DBLog $dbLog, MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );

		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$attachmentIdsInPageAttachments = [];
		foreach ( $this->workspaceDB->getPageAttachments() as $pageAttachment ) {
			if ( !isset( $pageAttachment['attachment_id'] ) ) {
				continue;
			}

			$attachmentIdsInPageAttachments[(int)$pageAttachment['attachment_id']] = true;
		}

		$filenameBuilder = new FilenameBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->migrationConfig
		);

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if (
				!isset( $attachment['attachment_id'] )
				|| !isset( $attachment['container_id'] )
				|| !isset( $attachment['space_id'] )
				|| !isset( $attachment['filename'] )
				|| !isset( $attachment['content_status'] )
				|| !isset( $attachment['original_version_id'] )
			) {
				continue;
			}

			if ( $attachment['original_version_id'] !== -1 ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			if ( isset( $attachmentIdsInPageAttachments[$attachmentId] ) ) {
				continue;
			}

			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Create wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			try {
				$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					''
				);
			} catch ( Exception $ex ) {
				$this->dbLog->addLogEntry(
					'warning',
					'analyze',
					__CLASS__,
					"Could not build target filename for attachment $attachmentId: "
					. $ex->getMessage()
				);
				continue;
			}

			// Uncollide file title
			$exists = ( $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle )
				|| $this->workspaceDB->checkAdditionalAttachmentWikiTitleExists( $attatchmentWikiTitle )
			);

			$counter = 1;
			$maxUncollideAttempts = 10000;
			while ( $exists ) {
				if ( $counter > $maxUncollideAttempts ) {
					$this->dbLog->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not find unique additional attachment title for attachment $attachmentId after "
						. (string)$maxUncollideAttempts . ' attempts'
					);
					continue 2;
				}

				$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					'',
					"-(" . (string)$counter . ")"
				);

				$exists = ( $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle )
					|| $this->workspaceDB->checkAdditionalAttachmentWikiTitleExists( $attatchmentWikiTitle )
				);
				$counter++;
			}

			$this->writeln(
				"Add additional attachment for attachment ID $attachmentId with title: $attatchmentWikiTitle"
			);

			$this->workspaceDB->addAdditionalAttachment(
				$attachmentId,
				(string)$attachment['filename'],
				$attatchmentWikiTitle
			);
		}
	}

}
