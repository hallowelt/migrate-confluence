<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;

/**
 * Populate additional_attachments with attachments that are not part of page_attachments
 * or blog_post_attachments.
 *
 * The target filename is built from space prefix and original filename only.
 */
class PopulateAdditionalAttachmentsTable extends AttachmentTableUpdaterBase {

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'additional';
	}

	/** @inheritDoc */
	protected function checkWikiTitleExists( string $wikiTitle ): bool {
		return ( $this->workspaceDB->checkPageAttachmentWikiTitleExists( $wikiTitle )
			|| $this->workspaceDB->checkBlogPostAttachmentWikiTitleExists( $wikiTitle )
			|| $this->workspaceDB->checkAdditionalAttachmentWikiTitleExists( $wikiTitle )
		);
	}

	/** @inheritDoc */
	protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void {
		$this->workspaceDB->addAdditionalAttachment( $attachmentId, $originalFilename, $targetFilename );
	}

	/** @inheritDoc */
	protected function getStoredAttachments(): array {
		return $this->workspaceDB->getAdditionalAttachments();
	}

	/**
	 * Adds attachments that are not already tracked in page_attachments or blog_post_attachments.
	 */
	protected function addAttachments(): void {
		$knownAttachmentIds = [];
		foreach ( $this->workspaceDB->getPageAttachments() as $pageAttachment ) {
			if ( isset( $pageAttachment['attachment_id'] ) ) {
				$knownAttachmentIds[(int)$pageAttachment['attachment_id']] = true;
			}
		}
		foreach ( $this->workspaceDB->getBlogPostAttachments() as $blogPostAttachment ) {
			if ( isset( $blogPostAttachment['attachment_id'] ) ) {
				$knownAttachmentIds[(int)$blogPostAttachment['attachment_id']] = true;
			}
		}

		$filenameBuilder = new FilenameBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->migrationConfig
		);

		/** @var array<int,array{containerId:int,origFilename:string,wikiTitle:string}> $collected */
		$collected = [];

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if (
				!isset( $attachment['attachment_id'] )
				|| !isset( $attachment['container_id'] )
				|| !isset( $attachment['space_id'] )
				|| !isset( $attachment['filename'] )
				|| !isset( $attachment['content_status'] )
			) {
				continue;
			}

			if ( $attachment['content_status'] !== 'current' ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			if ( isset( $knownAttachmentIds[$attachmentId] ) ) {
				continue;
			}

			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Creating wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			try {
				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					''
				);
			} catch ( Exception $ex ) {
				$this->dbLog->addLogEntry(
					'error',
					'analyze',
					__CLASS__,
					"Could not build target filename for attachment $attachmentId: "
					. $ex->getMessage()
				);
			}

			if ( empty( $attachmentWikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for attachment id $attachmentId";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);

				throw new Exception(
					$message
				);
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
					'',
					"-(" . (string)$counter . ")"
				);

				if ( empty( $attachmentWikiTitle ) ) {
					$message = "TitleCompressor delivers empty wiki title for "
					. "attachment id $attachmentId while uncolliding";

					$this->dbLog->addLogEntry(
						'error',
						'extract',
						__CLASS__,
						$message
					);

					throw new Exception(
						$message
					);
				}

				$exists = $this->checkWikiTitleExists( $attachmentWikiTitle );
				$counter++;
			}

			$collected[$attachmentId] = [
				'containerId' => (int)$attachment['container_id'],
				'origFilename' => (string)$attachment['filename'],
				'wikiTitle' => $attachmentWikiTitle,
			];
		}

		$collected = $this->compressWikiTitles( $collected );

		foreach ( $collected as $attachmentId => $data ) {
			$this->writeln(
				"Add {$this->getContentLabel()} attachment for attachment ID $attachmentId"
				. " with title: {$data['wikiTitle']}"
			);

			$this->storeAttachment(
				$attachmentId,
				$data['containerId'],
				$data['origFilename'],
				$data['wikiTitle']
			);
		}
	}
}
