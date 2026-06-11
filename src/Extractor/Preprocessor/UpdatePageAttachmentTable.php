<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use SplFileInfo;

/**
 */
class UpdatePageAttachmentTable extends ProcessorBase {

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
		$this->addPageAttachments();
		$this->checkWikiTitles();
	}

	private function addPageAttachments(): void {
		$pageIdToWikiTitleMap = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			if ( !isset( $page['page_id'] )
				|| !isset( $page['wiki_title'] )
			) {
				continue;
			}
			$pageIdToWikiTitleMap[(int)$page['page_id']] = (string)$page['wiki_title'];
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			return;
		}

		$filenameBuilder = new FilenameBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->migrationConfig
		);

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if (
				!isset( $attachment['attachment_id'] )
				|| !isset( $attachment['space_id'] )
				|| !isset( $attachment['filename'] )
				|| !isset( $attachment['container_id'] )
			) {
				continue;
			}

			$pageId = (int)$attachment['container_id'];
			if ( !isset( $pageIdToWikiTitleMap[$pageId] ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Creating wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			$pageWikiTitle = $pageIdToWikiTitleMap[$pageId];
			$pageWikiTitle = substr( $pageWikiTitle, strrpos( $pageWikiTitle, ':' ) );
			$pageWikiTitleParts = explode( '/', $pageWikiTitle );
			$shortPageWikiTitle = end( $pageWikiTitleParts );
			try {
				$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortPageWikiTitle,
				);
			} catch ( Exception $fallbackEx ) {
				$this->dbLog->addLogEntry(
					'warning',
					'analyze',
					__CLASS__,
					"Could not build target filename for attachment $attachmentId: "
					. $fallbackEx->getMessage()
				);
				continue;
			}

			// Uncollide file title
			$exists = $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle );
			$counter = 1;
			$maxUncollideAttempts = 10000;
			while ( $exists ) {
				if ( $counter > $maxUncollideAttempts ) {
					$this->dbLog->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not find unique page attachment title for attachment $attachmentId after "
						. (string)$maxUncollideAttempts . ' attempts'
					);
					continue 2;
				}

				$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortPageWikiTitle,
					"-(" . (string)$counter . ")"
				);

				$exists = $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle );
				$counter++;
			}

			$file = new SplFileInfo( $attatchmentWikiTitle );
			if ( $file->getExtension() === '' || strlen( $file->getExtension() ) > 10 ) {
				$attatchmentWikiTitle .= '.unknown';
			}

			$this->writeln(
				"Add page attachment for attachment ID $attachmentId with title: $attatchmentWikiTitle"
			);

			$this->workspaceDB->addPageAttachment(
				$attachmentId,
				$pageId,
				$attachment['filename'],
				$attatchmentWikiTitle
			);
		}
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$pageAttachments = $this->workspaceDB->getPageAttachments();
		$validityChecker = new TitleValidityChecker();
		foreach ( $pageAttachments as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$wikiTitle = $attachment['target_attachment_filename'];
			if ( !$validityChecker->hasValidLength( $wikiTitle ) ) {
				$this->workspaceDB->addInvalidAttachmentTitle(
					$attachmentId,
					$wikiTitle,
					'Attachment title contains to many characters (>256)'
				);
			}
		}
	}
}
