<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

/**
 * Populates the page_attachments table with wiki titles for all page attachments.
 */
class UpdatePageAttachmentTable extends AttachmentTableUpdaterBase {

	/** @inheritDoc */
	protected function getContentItems(): array {
		return $this->workspaceDB->getPages();
	}

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'page';
	}

	/** @inheritDoc */
	protected function checkWikiTitleExists( string $wikiTitle ): bool {
		return $this->workspaceDB->checkPageAttachmentWikiTitleExists( $wikiTitle );
	}

	/** @inheritDoc */
	protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void {
		if ($attachmentId === 83012159) {
			$foo = 'bar';
		}
		$this->workspaceDB->addPageAttachment(
			$attachmentId, $containerId, $originalFilename, $targetFilename
		);
	}

	/** @inheritDoc */
	protected function getStoredAttachments(): array {
		return $this->workspaceDB->getPageAttachments();
	}
}
