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

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): bool {
		return $this->db->updatePageWikiTitle( $pageId, $wikiTitle );
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageWikiTitle( int $pageId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidPageWikiTitle( $pageId, $wikiTitle, $text );
	}

	/**
	 * @param int $pageId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updatePageBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		return $this->db->updatePageBodyContentIds( $pageId, $bodyContentIds );
	}

	/**
	 * @param int $pageId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateBlogPostBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		return $this->db->updateBlogPostBodyContentIds( $pageId, $bodyContentIds );
	}

	/**
	 * @param int $commentId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateCommentBodyContentIds( int $commentId, array $bodyContentIds ): bool {
		return $this->db->updateCommentBodyContentIds( $commentId, $bodyContentIds );
	}

	/**
	 * @param int $spaceDescriptionId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateSpaceDescriptionBodyContentIds( int $spaceDescriptionId, array $bodyContentIds ): bool {
		return $this->db->updateSpaceDescriptionBodyContentIds( $spaceDescriptionId, $bodyContentIds );
	}

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 *
	 * @return bool
	 */
	public function updateBlogPostSpaceId( int $pageId, int $spaceId ): bool {
		return $this->db->updateBlogPostSpaceId( $pageId, $spaceId );
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): bool {
		return $this->db->updateBlogPostWikiTitle( $pageId, $wikiTitle );
	}

	/**
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidBlogPostWikiTitle( int $blogPostId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidBlogPostWikiTitle( $blogPostId, $wikiTitle, $text );
	}

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updatePageTemplateWikiTitle( int $templateId, string $wikiTitle ): bool {
		return $this->db->updatePageTemplateWikiTitle( $templateId, $wikiTitle );
	}

	/**
	 * @param int $attachmentId
	 * @param int $pageId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 *
	 * @return bool
	 */
	public function addPageAttachment(
		int $attachmentId,
		int $pageId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addPageAttachment(
			$attachmentId, $pageId, $originalAttachmentFilename, $targetAttachmentFilename
		);
	}

	/**
	 * @param int $attachmentId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidAttachmentTitle( int $attachmentId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidAttachmentTitle( $attachmentId, $wikiTitle, $text );
	}

	/**
	 * @param int $attachmentId
	 * @param int $blogPostId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 *
	 * @return bool
	 */
	public function addBlogPostAttachment(
		int $attachmentId,
		int $blogPostId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addBlogPostAttachment(
			$attachmentId, $blogPostId, $originalAttachmentFilename, $targetAttachmentFilename
		);
	}

	/**
	 * @param int $attachmentId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 *
	 * @return bool
	 */
	public function addAdditionalAttachment(
		int $attachmentId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addAdditionalAttachment(
			$attachmentId, $originalAttachmentFilename, $targetAttachmentFilename
		);
	}

	/**
	 * @param int $pageId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addPageMeta( int $pageId, array $meta ): bool {
		return $this->db->addPageMeta( $pageId, $meta );
	}

	/**
	 * @param int $pageId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addBlogPostMeta( int $pageId, array $meta ): bool {
		return $this->db->addBlogPostMeta( $pageId, $meta );
	}

	/**
	 * @param int $attachmentId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addAttachmentMeta( int $attachmentId, array $meta ): bool {
		return $this->db->addAttachmentMeta( $attachmentId, $meta );
	}

	/**
	 * @param int $attachmentId
	 * @param string $description
	 *
	 * @return void
	 */
	public function addAttachmentDescription( int $attachmentId, string $description ): void {
		$this->db->addAttachmentDescription( $attachmentId, $description );
	}

	/**
	 * @param int $commentId
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function addPageComment( int $commentId, int $pageId, string $wikiTitle ): bool {
		return $this->db->addPageComment( $commentId, $pageId, $wikiTitle );
	}

	/**
	 * @param int $commentId
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function addBlogPostComment( int $commentId, int $blogPostId, string $wikiTitle ): bool {
		return $this->db->addBlogPostComment( $commentId, $blogPostId, $wikiTitle );
	}
}
