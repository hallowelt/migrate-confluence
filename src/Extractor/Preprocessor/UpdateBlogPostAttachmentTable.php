<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

/**
 * Populates the blog_post_attachments table with wiki titles for all blog post attachments.
 */
class UpdateBlogPostAttachmentTable extends AttachmentTableUpdaterBase {

	/** @inheritDoc */
	protected function getContentItems(): array {
		return $this->dataReader->getCurrentBlogPosts();
	}

	/** @inheritDoc */
	protected function getContentLabel(): string {
		return 'blog post';
	}

	/** @inheritDoc */
	protected function checkWikiTitleExists( string $wikiTitle ): bool {
		return $this->dataReader->checkBlogPostAttachmentWikiTitleExists( $wikiTitle );
	}

	/** @inheritDoc */
	protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void {
		$this->writer->addBlogPostAttachment(
			$attachmentId, $containerId, $originalFilename, $targetFilename
		);
	}

	/** @inheritDoc */
	protected function getStoredAttachments(): array {
		return $this->dataReader->getBlogPostAttachments();
	}
}
