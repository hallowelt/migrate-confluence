<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\AbstractDirectDataWriter;

class ExtractorDirectDataWriter extends AbstractDirectDataWriter implements IExtractorDataWriter {

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidPageTemplateTitle( $templateId, $wikiTitle, $text );
	}

	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): bool {
		return $this->db->updatePageWikiTitle( $pageId, $wikiTitle );
	}

	public function addInvalidPageWikiTitle( int $pageId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidPageWikiTitle( $pageId, $wikiTitle, $text );
	}

	public function updatePageBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		return $this->db->updatePageBodyContentIds( $pageId, $bodyContentIds );
	}

	public function updateBlogPostBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		return $this->db->updateBlogPostBodyContentIds( $pageId, $bodyContentIds );
	}

	public function updateCommentBodyContentIds( int $commentId, array $bodyContentIds ): bool {
		return $this->db->updateCommentBodyContentIds( $commentId, $bodyContentIds );
	}

	public function updateSpaceDescriptionBodyContentIds( int $spaceDescriptionId, array $bodyContentIds ): bool {
		return $this->db->updateSpaceDescriptionBodyContentIds( $spaceDescriptionId, $bodyContentIds );
	}

	public function updateBlogPostSpaceId( int $pageId, int $spaceId ): bool {
		return $this->db->updateBlogPostSpaceId( $pageId, $spaceId );
	}

	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): bool {
		return $this->db->updateBlogPostWikiTitle( $pageId, $wikiTitle );
	}

	public function addInvalidBlogPostWikiTitle( int $blogPostId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidBlogPostWikiTitle( $blogPostId, $wikiTitle, $text );
	}

	public function updatePageTemplateWikiTitle( int $templateId, string $wikiTitle ): bool {
		return $this->db->updatePageTemplateWikiTitle( $templateId, $wikiTitle );
	}

	public function addPageAttachment(
		int $attachmentId,
		int $pageId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addPageAttachment( $attachmentId, $pageId, $originalAttachmentFilename, $targetAttachmentFilename );
	}

	public function addInvalidAttachmentTitle( int $attachmentId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidAttachmentTitle( $attachmentId, $wikiTitle, $text );
	}

	public function addBlogPostAttachment(
		int $attachmentId,
		int $blogPostId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addBlogPostAttachment( $attachmentId, $blogPostId, $originalAttachmentFilename, $targetAttachmentFilename );
	}

	public function addAdditionalAttachment(
		int $attachmentId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addAdditionalAttachment( $attachmentId, $originalAttachmentFilename, $targetAttachmentFilename );
	}

	public function addPageMeta( int $pageId, array $meta ): bool {
		return $this->db->addPageMeta( $pageId, $meta );
	}

	public function addBlogPostMeta( int $pageId, array $meta ): bool {
		return $this->db->addBlogPostMeta( $pageId, $meta );
	}

	public function addAttachmentMeta( int $attachmentId, array $meta ): bool {
		return $this->db->addAttachmentMeta( $attachmentId, $meta );
	}

	public function addAttachmentDescription( int $attachmentId, string $description ): void {
		$this->db->addAttachmentDescription( $attachmentId, $description );
	}

	public function addPageComment( int $commentId, int $pageId, string $wikiTitle ): bool {
		return $this->db->addPageComment( $commentId, $pageId, $wikiTitle );
	}

	public function addBlogPostComment( int $commentId, int $blogPostId, string $wikiTitle ): bool {
		return $this->db->addBlogPostComment( $commentId, $blogPostId, $wikiTitle );
	}
}
