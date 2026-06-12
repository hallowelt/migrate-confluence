<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

/**
 * Populates the blog_post_attachments table with wiki titles for all blog post attachments.
 */
class UpdateBlogPostAttachmentTable extends AttachmentTableUpdaterBase {

	/** @inheritDoc */
	protected function getContentItems(): array {
		return $this->workspaceDB->getBlogPosts();
	}

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'blog post';
	}

	/** @inheritDoc */
	protected function checkWikiTitleExists( string $wikiTitle ): bool {
		return $this->workspaceDB->checkBlogPostAttachmentWikiTitleExists( $wikiTitle );
	}

	/** @inheritDoc */
	protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void {
		$this->workspaceDB->addBlogPostAttachment(
			$attachmentId, $containerId, $originalFilename, $targetFilename
		);
	}

	/** @inheritDoc */
	protected function getStoredAttachments(): array {
		return $this->workspaceDB->getBlogPostAttachments();
	}
}
