<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataReader;

use HalloWelt\MigrateConfluence\Database\DataReader\AbstractDataReader;

class ExtractorDataReader extends AbstractDataReader {

	public function checkAdditionalAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkAdditionalAttachmentWikiTitleExists( $wikiTitle );
	}

	public function checkBlogPostAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkBlogPostAttachmentWikiTitleExists( $wikiTitle );
	}

	public function checkPageAttachmentWikiTitleExists( string $wikiTitle ): bool {
		return $this->db->checkPageAttachmentWikiTitleExists( $wikiTitle );
	}

	public function getAdditionalAttachments( ?int $spaceId = null ): array {
		return $this->db->getAdditionalAttachments( $spaceId );
	}

	public function getAttachmentMetaById( int $attachmentId ): array {
		return $this->db->getAttachmentMetaById( $attachmentId );
	}

	public function getAttachments(): array {
		return $this->db->getAttachments();
	}

	public function getBlogPostAttachments( ?int $spaceId = null ): array {
		return $this->db->getBlogPostAttachments( $spaceId );
	}

	public function getBlogPosts(): array {
		return $this->db->getBlogPosts();
	}

	public function getBodyContentBodyByBodyContentId( int $bodyContentId ): ?string {
		return $this->db->getBodyContentBodyByBodyContentId( $bodyContentId );
	}

	public function getBodyContentIdsForContentId( int $contentId ): array {
		return $this->db->getBodyContentIdsForContentId( $contentId );
	}

	public function getComments(): array {
		return $this->db->getComments();
	}

	public function getCommentsForBlogPosts( ?int $spaceId = null ): array {
		return $this->db->getCommentsForBlogPosts( $spaceId );
	}

	public function getCommentsForPages( ?int $spaceId = null ): array {
		return $this->db->getCommentsForPages( $spaceId );
	}

	public function getCurrentAttachments(): array {
		return $this->db->getCurrentAttachments();
	}

	public function getCurrentBlogPosts(): array {
		return $this->db->getCurrentBlogPosts();
	}

	public function getCurrentComments(): array {
		return $this->db->getCurrentComments();
	}

	public function getCurrentPages(): array {
		return $this->db->getCurrentPages();
	}

	public function getCurrentPageTemplateContents(): array {
		return $this->db->getCurrentPageTemplateContents();
	}

	public function getCurrentSpaceDescriptions(): array {
		return $this->db->getCurrentSpaceDescriptions();
	}

	public function getInvalidAttachmentTitles(): array {
		return $this->db->getInvalidAttachmentTitles();
	}

	public function getInvalidBlogPostWikiTitles(): array {
		return $this->db->getInvalidBlogPostWikiTitles();
	}

	public function getInvalidPageWikiTitles(): array {
		return $this->db->getInvalidPageWikiTitles();
	}

	public function getLabelById( int $labelId ): ?array {
		return $this->db->getLabelById( $labelId );
	}

	public function getLabellingById( int $labellingId ): ?array {
		return $this->db->getLabellingById( $labellingId );
	}

	public function getMapPageIdToConfluenceTitle(): array {
		return $this->db->getMapPageIdToConfluenceTitle();
	}

	public function getMapPageIdtoParentPageId(): array {
		return $this->db->getMapPageIdtoParentPageId();
	}

	public function getMapSpaceIdToHomepageId(): array {
		return $this->db->getMapSpaceIdToHomepageId();
	}

	public function getMapSpaceIdToKey(): array {
		return $this->db->getMapSpaceIdToKey();
	}

	public function getMapSpaceIdToPrefix(): array {
		return $this->db->getMapSpaceIdToPrefix();
	}

	public function getPageAttachments( ?int $spaceId = null ): array {
		return $this->db->getPageAttachments( $spaceId );
	}

	public function getPages(): array {
		return $this->db->getPages();
	}

	public function getPageTemplates(): array {
		return $this->db->getPageTemplates();
	}

	public function getSpaceDescriptions(): array {
		return $this->db->getSpaceDescriptions();
	}

	public function getSpaceIdForPageId( int $pageId ): ?int {
		return $this->db->getSpaceIdForPageId( $pageId );
	}

	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string {
		return $this->db->getSpaceKeyFromSpaceId( $spaceId );
	}

	public function getSpaces(): array {
		return $this->db->getSpaces();
	}

	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
	}

	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getWikiPageTitleFromPageId( $pageId );
	}
}
