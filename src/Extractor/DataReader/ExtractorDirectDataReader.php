<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataReader;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class ExtractorDirectDataReader implements IExtractorDataReader {

	public function __construct(
		private WorkspaceDB $db
	) {
	}

	/**
	 * @param string $step
	 * @param string $type
	 *
	 * @return array
	 */
	public function getLogEntriesForStep( string $step, string $type = '' ): array {
		return $this->db->getLogEntriesForStep( $step, $type );
	}

	/**
	 * @return array
	 */
	public function getSpaces(): array {
		return $this->db->getSpaces();
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array {
		return $this->db->getMapSpaceIdToPrefix();
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToKey(): array {
		return $this->db->getMapSpaceIdToKey();
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToHomepageId(): array {
		return $this->db->getMapSpaceIdToHomepageId();
	}

	/**
	 * @param int $spaceId
	 *
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string {
		return $this->db->getSpaceKeyFromSpaceId( $spaceId );
	}

	/**
	 * @return array
	 */
	public function getSpaceDescriptions(): array {
		return $this->db->getSpaceDescriptions();
	}

	/**
	 * @return array
	 */
	public function getCurrentSpaceDescriptions(): array {
		return $this->db->getCurrentSpaceDescriptions();
	}

	/**
	 * @return array
	 */
	public function getPages(): array {
		return $this->db->getPages();
	}

	/**
	 * @return array
	 */
	public function getCurrentPages(): array {
		return $this->db->getCurrentPages();
	}

	/**
	 * @return array
	 */
	public function getMapPageIdtoParentPageId(): array {
		return $this->db->getMapPageIdtoParentPageId();
	}

	/**
	 * @return array
	 */
	public function getMapPageIdToConfluenceTitle(): array {
		return $this->db->getMapPageIdToConfluenceTitle();
	}

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getWikiPageTitleFromPageId( $pageId );
	}

	/**
	 * @param int $pageId
	 *
	 * @return int|null
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int {
		return $this->db->getSpaceIdForPageId( $pageId );
	}

	/**
	 * @return array
	 */
	public function getInvalidPageWikiTitles(): array {
		return $this->db->getInvalidPageWikiTitles();
	}

	/**
	 * @return array
	 */
	public function getBlogPosts(): array {
		return $this->db->getBlogPosts();
	}

	/**
	 * @return array
	 */
	public function getCurrentBlogPosts(): array {
		return $this->db->getCurrentBlogPosts();
	}

	/**
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/**
	 * @return array
	 */
	public function getInvalidBlogPostWikiTitles(): array {
		return $this->db->getInvalidBlogPostWikiTitles();
	}

	/**
	 * @param int $contentId
	 *
	 * @return array
	 */
	public function getBodyContentIdsForContentId( int $contentId ): array {
		return $this->db->getBodyContentIdsForContentId( $contentId );
	}

	/**
	 * @param int $bodyContentId
	 *
	 * @return string|null
	 */
	public function getBodyContentBodyByBodyContentId( int $bodyContentId ): ?string {
		return $this->db->getBodyContentBodyByBodyContentId( $bodyContentId );
	}

	/**
	 * @return array
	 */
	public function getAttachments(): array {
		return $this->db->getAttachments();
	}

	/**
	 * @return array
	 */
	public function getCurrentAttachments(): array {
		return $this->db->getCurrentAttachments();
	}

	/**
	 * @return array
	 */
	public function getPageAttachments(): array {
		return $this->db->getPageAttachments();
	}

	/**
	 * @return array
	 */
	public function getBlogPostAttachments(): array {
		return $this->db->getBlogPostAttachments();
	}

	/**
	 * @return array
	 */
	public function getAdditionalAttachments(): array {
		return $this->db->getAdditionalAttachments();
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkPageAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkPageAttachmentWikiTitleExists( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkBlogPostAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkBlogPostAttachmentWikiTitleExists( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkAdditionalAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkAdditionalAttachmentWikiTitleExists( $wikiTitle );
	}

	/**
	 * @param int $attachmentId
	 *
	 * @return array
	 */
	public function getAttachmentMetaById( int $attachmentId ): array {
		return $this->db->getAttachmentMetaById( $attachmentId );
	}

	/**
	 * @return array
	 */
	public function getInvalidAttachmentTitles(): array {
		return $this->db->getInvalidAttachmentTitles();
	}

	/**
	 * @return array
	 */
	public function getComments(): array {
		return $this->db->getComments();
	}

	/**
	 * @return array
	 */
	public function getCurrentComments(): array {
		return $this->db->getCurrentComments();
	}

	/**
	 * @return array
	 */
	public function getCommentsForPages(): array {
		return $this->db->getCommentsForPages();
	}

	/**
	 * @return array
	 */
	public function getCommentsForBlogPosts(): array {
		return $this->db->getCommentsForBlogPosts();
	}

	/**
	 * @param int $labellingId
	 *
	 * @return array|null
	 */
	public function getLabellingById( int $labellingId ): ?array {
		return $this->db->getLabellingById( $labellingId );
	}

	/**
	 * @param int $labelId
	 *
	 * @return array|null
	 */
	public function getLabelById( int $labelId ): ?array {
		return $this->db->getLabelById( $labelId );
	}

	/**
	 * @return array
	 */
	public function getPageTemplates(): array {
		return $this->db->getPageTemplates();
	}

	/**
	 * @return array
	 */
	public function getCurrentPageTemplateContents(): array {
		return $this->db->getCurrentPageTemplateContents();
	}
}
