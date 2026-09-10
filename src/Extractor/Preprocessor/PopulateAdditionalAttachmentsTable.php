<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

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
		$this->writer->addAdditionalAttachment( $attachmentId, $originalFilename, $targetFilename );
	}

	/** @inheritDoc */
	protected function getStoredAttachments(): array {
		return $this->workspaceDB->getAdditionalAttachments();
	}

	protected function isValid( array $attachment ): bool {
		if ( !parent::isValid( $attachment ) ) {
			return false;
		}

		if ( isset( $attachment['space_id'] ) && $attachment['space_id'] !== '' ) {
			return true;
		}

		$attachmentId = (int)$attachment['attachment_id'];
		$this->dbLog->addLogEntry(
			'warning',
			'extract',
			__CLASS__,
			"Could not resolve a space for attachment ID $attachmentId; skipping."
		);
		return false;
	}

	/**
	 * Adds attachments that are not already tracked in page_attachments or blog_post_attachments.
	 *
	 * @throws \Exception
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
			$this->getSpaceIdToPrefixMapWithConfigOverrides(),
			$this->migrationConfig
		);

		/** @var array<int,array{containerId:int,origFilename:string,wikiTitle:string}> $collected */
		$collected = [];

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if ( !$this->isValid( $attachment ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			if ( isset( $knownAttachmentIds[$attachmentId] ) ) {
				continue;
			}

			$containerId = (int)$attachment['container_id'];
			$attachmentSpaceId = (int)$attachment['space_id'];

			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Creating wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			$attachmentWikiTitle = $this->buildUniqueAttachmentWikiTitle(
				$filenameBuilder,
				$attachmentId,
				$attachmentSpaceId,
				$attachmentOrigFilename,
				'',
				'error'
			);
			if ( $attachmentWikiTitle === null ) {
				continue;
			}

			$collected[$attachmentId] = [
				'containerId' => $containerId,
				'origFilename' => (string)$attachment['filename'],
				'wikiTitle' => $attachmentWikiTitle,
			];
		}

		$this->finalizeAndStoreAttachments( $collected );
	}

	/**
	 * Builds the space_id => prefix map used for additional attachment titles.
	 * Configured space-prefix values override DB prefixes by matching space keys.
	 *
	 * @return array
	 */
	protected function getSpaceIdToPrefixMapWithConfigOverrides(): array {
		$spaceIdToPrefixMap = $this->workspaceDB->getMapSpaceIdToPrefix();
		$spaceIdToKeyMap = $this->workspaceDB->getMapSpaceIdToKey();

		foreach ( $spaceIdToKeyMap as $spaceId => $spaceKey ) {
			$configPrefix = $this->migrationConfig->getPrefixFromSpaceKeyToPrefixMap( (string)$spaceKey );
			if ( $configPrefix === null ) {
				continue;
			}

			if ( $configPrefix !== '' && strpos( $configPrefix, ':' ) === false ) {
				$configPrefix .= ':';
			}

			$spaceIdToPrefixMap[(int)$spaceId] = $configPrefix;
		}

		return $spaceIdToPrefixMap;
	}
}
