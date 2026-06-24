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
 * Base class for preprocessors that populate an attachment table
 * (either page attachments or blog post attachments).
 *
 * Subclasses provide the content-type-specific DB operations while
 * this class owns the shared title-building and collision-resolution logic.
 */
abstract class AttachmentTableUpdaterBase extends ProcessorBase {

	protected const MAX_UNCOLLIDE_ATTEMPTS = 10000;
	protected const UNKNOWN_EXTENSION = '.unknown';

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, DBLog $dbLog, protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addAttachments();
		$this->checkWikiTitles();
	}

	/**
	 * Returns all content items (pages or blog posts) as arrays with at least
	 * 'page_id' and 'wiki_title' keys.
	 * Subclasses that rely on the default addAttachments() implementation must override this.
	 *
	 * @return array
	 */
	protected function getContentItems(): array {
		return [];
	}

	/**
	 * Returns a human-readable label for the content type, used in log messages.
	 * E.g. "page" or "blog post".
	 *
	 * @return string
	 */
	abstract protected function getContentLabel(): string;

	/**
	 * Checks whether a wiki title already exists in the attachment table.
	 *
	 * @param string $wikiTitle
	 * @return bool
	 */
	abstract protected function checkWikiTitleExists( string $wikiTitle ): bool;

	/**
	 * Persists a new attachment entry to the attachment table.
	 *
	 * @param int $attachmentId
	 * @param int $containerId
	 * @param string $originalFilename
	 * @param string $targetFilename
	 * @return void
	 */
	abstract protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void;

	/**
	 * Returns all stored attachment entries for validity checking.
	 *
	 * @return array
	 */
	abstract protected function getStoredAttachments(): array;

	protected function addAttachments(): void {
		$contentIdToWikiTitleMap = [];
		foreach ( $this->getContentItems() as $item ) {
			if ( !isset( $item['page_id'] ) || !isset( $item['wiki_title'] ) ) {
				continue;
			}
			$contentIdToWikiTitleMap[(int)$item['page_id']] = (string)$item['wiki_title'];
		}

		if ( $contentIdToWikiTitleMap === [] ) {
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
				|| !isset( $attachment['content_status'] )
			) {
				continue;
			}

			if ( $attachment['content_status'] !== 'current' ) {
				continue;
			}

			$containerId = (int)$attachment['container_id'];
			if ( !isset( $contentIdToWikiTitleMap[$containerId] ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Creating wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			$contentWikiTitle = $contentIdToWikiTitleMap[$containerId];
			$contentWikiTitle = substr( $contentWikiTitle, strrpos( $contentWikiTitle, ':' ) + 1 );
			$contentWikiTitleParts = explode( '/', $contentWikiTitle );
			$shortContentWikiTitle = end( $contentWikiTitleParts );

			try {
				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortContentWikiTitle,
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
			$exists = $this->checkWikiTitleExists( $attachmentWikiTitle );
			$counter = 1;
			while ( $exists ) {
				if ( $counter > self::MAX_UNCOLLIDE_ATTEMPTS ) {
					$this->dbLog->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not find unique {$this->getContentLabel()} attachment title for attachment "
						. "$attachmentId after " . (string)self::MAX_UNCOLLIDE_ATTEMPTS . ' attempts'
					);
					continue 2;
				}

				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortContentWikiTitle,
					"-(" . (string)$counter . ")"
				);

				$exists = $this->checkWikiTitleExists( $attachmentWikiTitle );
				$counter++;
			}

			$file = new SplFileInfo( $attachmentWikiTitle );
			if ( $file->getExtension() === '' || strlen( $file->getExtension() ) > 10 ) {
				$attachmentWikiTitle .= self::UNKNOWN_EXTENSION;
			}

			$this->writeln(
				"Add {$this->getContentLabel()} attachment for attachment ID $attachmentId"
				. " with title: $attachmentWikiTitle"
			);

			$this->storeAttachment(
				$attachmentId,
				$containerId,
				$attachment['filename'],
				$attachmentWikiTitle
			);
		}
	}

	private function checkWikiTitles(): void {
		$validityChecker = new TitleValidityChecker();
		foreach ( $this->getStoredAttachments() as $attachment ) {
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
