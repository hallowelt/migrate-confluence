<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataWriter;

use HalloWelt\MediaWiki\Lib\Migration\Database\DataWriter\IDataWriter;

interface IExtractorDataWriter extends IDataWriter {

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void;

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): bool;

	/**
	 * @param int $pageId
	 * @param string $interwikiTitle
	 *
	 * @return bool
	 */
	public function updatePageInterwikiTitle( int $pageId, string $interwikiTitle ): bool;

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 *
	 * @return bool
	 */
	public function updatePageSpaceId( int $pageId, int $spaceId ): bool;

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageWikiTitle( int $pageId, string $wikiTitle, string $text ): void;

	/**
	 * @param int $pageId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updatePageBodyContentIds( int $pageId, array $bodyContentIds ): bool;

	/**
	 * @param int $pageId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateBlogPostBodyContentIds( int $pageId, array $bodyContentIds ): bool;

	/**
	 * @param int $commentId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateCommentBodyContentIds( int $commentId, array $bodyContentIds ): bool;

	/**
	 * @param int $spaceDescriptionId
	 * @param array $bodyContentIds
	 *
	 * @return bool
	 */
	public function updateSpaceDescriptionBodyContentIds( int $spaceDescriptionId, array $bodyContentIds ): bool;

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 *
	 * @return bool
	 */
	public function updateBlogPostSpaceId( int $pageId, int $spaceId ): bool;

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): bool;

	/**
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidBlogPostWikiTitle( int $blogPostId, string $wikiTitle, string $text ): void;

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function updatePageTemplateWikiTitle( int $templateId, string $wikiTitle ): bool;

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
	): bool;

	/**
	 * @param int $attachmentId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidAttachmentTitle( int $attachmentId, string $wikiTitle, string $text ): void;

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
	): bool;

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
	): bool;

	/**
	 * @param int $pageId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addPageMeta(
		int $pageId, array $meta
	): bool;

	/**
	 * @param int $pageId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addBlogPostMeta(
		int $pageId, array $meta
	): bool;

	/**
	 * @param int $attachmentId
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function addAttachmentMeta(
		int $attachmentId, array $meta
	): bool;

	/**
	 * @param int $attachmentId
	 * @param string $description
	 *
	 * @return void
	 */
	public function addAttachmentDescription( int $attachmentId, string $description ): void;

	/**
	 * @param int $commentId
	 * @param int $pageId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function addPageComment( int $commentId, int $pageId, string $wikiTitle ): bool;

	/**
	 * @param int $commentId
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function addBlogPostComment( int $commentId, int $blogPostId, string $wikiTitle ): bool;
}
