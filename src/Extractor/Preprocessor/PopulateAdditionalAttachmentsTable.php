<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;

/**
 * Populate additional_attachments with attachments that are not part of page_attachments.
 *
 * The target filename is built from space prefix and original filename only.
 */
class PopulateAdditionalAttachmentsTable extends ProcessorBase {

	private const MAX_UNCOLLIDE_ATTEMPTS = 10000;

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
		$this->addAdditionalAttachments();
		$this->checkWikiTitles();
	}

	private function addAdditionalAttachments(): void {
		$attachmentIdsInPageAttachments = [];
		foreach ( $this->workspaceDB->getPageAttachments() as $pageAttachment ) {
			if ( !isset( $pageAttachment['attachment_id'] ) ) {
				continue;
			}

			$attachmentIdsInPageAttachments[(int)$pageAttachment['attachment_id']] = true;
		}
		foreach ( $this->workspaceDB->getBlogPostAttachments() as $blogPostAttachment ) {
			if ( !isset( $blogPostAttachment['attachment_id'] ) ) {
				continue;
			}

			$attachmentIdsInPageAttachments[(int)$blogPostAttachment['attachment_id']] = true;
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
			) {
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
				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
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
			$exists = ( $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attachmentWikiTitle )
				|| $this->workspaceDB->checkAdditionalAttachmentWikiTitleExists( $attachmentWikiTitle )
			);

			$counter = 1;
			while ( $exists ) {
				if ( $counter > self::MAX_UNCOLLIDE_ATTEMPTS ) {
					$this->dbLog->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not find unique additional attachment title for attachment $attachmentId after "
						. (string)self::MAX_UNCOLLIDE_ATTEMPTS . ' attempts'
					);
					continue 2;
				}

				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					'',
					"-(" . (string)$counter . ")"
				);

				$exists = ( $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attachmentWikiTitle )
					|| $this->workspaceDB->checkAdditionalAttachmentWikiTitleExists( $attachmentWikiTitle )
				);
				$counter++;
			}

			$this->writeln(
				"Add additional attachment for attachment ID $attachmentId with title: $attachmentWikiTitle"
			);

			$this->workspaceDB->addAdditionalAttachment(
				$attachmentId,
				(string)$attachment['filename'],
				$attachmentWikiTitle
			);
		}
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$additionalAttachments = $this->workspaceDB->getAdditionalAttachments();
		$validityChecker = new TitleValidityChecker();
		foreach ( $additionalAttachments as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$wikiTitle = $attachment['target_attachment_filename'];
			if ( !$validityChecker->hasValidLength( $wikiTitle ) ) {
				$this->workspaceDB->addInvalidAttachmentTitle(
					$attachmentId,
					$wikiTitle,
					'Attachment title contains too many characters (>255)'
				);
			}
		}
	}
}
