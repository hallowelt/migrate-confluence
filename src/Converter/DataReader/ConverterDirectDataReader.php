<?php

namespace HalloWelt\MigrateConfluence\Converter\DataReader;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

/**
 * Direct workspace database reader for the convert step.
 */
class ConverterDirectDataReader implements IConverterDataReader {

	public function __construct(
		private WorkspaceDB $db
	) {
	}

	/** @param string $userKey @return string|null */
	public function getUsernameFromUserKey( string $userKey ): ?string {
		return $this->db->getUsernameFromUserKey( $userKey );
	}

	/** @return array */
	public function getMapSpaceIdToPrefix(): array {
		return $this->db->getMapSpaceIdToPrefix();
	}

	/**
	 * @param string $spaceKey
	 * @return int|null
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): ?int {
		return $this->db->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string {
		return $this->db->getSpaceKeyFromSpaceId( $spaceId );
	}

	/**
	 * @param string $spaceKey
	 * @return string
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): string {
		return $this->db->getSpacePrefixFromSpaceKey( $spaceKey );
	}

	/**
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceMainPageWikiTitleForSpaceId( int $spaceId ): ?string {
		return $this->db->getSpaceMainPageWikiTitleForSpaceId( $spaceId );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return string|null
	 */
	public function getWikiPageTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string {
		return $this->db->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return array|null
	 */
	public function getPageTitlesFromSpaceId( int $spaceId, string $confluenceTitle ): ?array {
		return $this->db->getPageTitlesFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * @param string $spaceKey
	 * @return string|null
	 */
	public function getWikisConfigWikiNameForSpaceKey( string $spaceKey ): ?string {
		return $this->db->getWikisConfigWikiNameForSpaceKey( $spaceKey );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string {
		return $this->db->getWikiBlogPostTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string|null
	 */
	public function getWikiFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): ?string {
		return $this->db->getWikiFileTitleFromSpaceId(
			$spaceId, $confluenceTitle, $originalAttachmentFilename
		);
	}

	/** @param int $spaceId @param string $rawPageTitle @return array */
	public function getAttachmentMetadataForPage( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getAttachmentMetadataForPage( $spaceId, $rawPageTitle );
	}

	/** @param int $spaceId @param string $rawPageTitle @return array */
	public function getAttachmentMetadataForBlogPost( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getAttachmentMetadataForBlogPost( $spaceId, $rawPageTitle );
	}

	/** @param string $attachmentTargetFileTitle @return string|null */
	public function getAttachmentReference( string $attachmentTargetFileTitle ): ?string {
		return $this->db->getAttachmentReference( $attachmentTargetFileTitle );
	}

	/** @param int $spaceId @param string $rawPageTitle @return array */
	public function getWikiFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getWikiFileTitlesForPage( $spaceId, $rawPageTitle );
	}

	/** @param int $spaceId @param string $rawPageTitle @return array */
	public function getWikiFileTitlesForBlogPost( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getWikiFileTitlesForBlogPost( $spaceId, $rawPageTitle );
	}

	/** @param int $pageId @return array */
	public function getPageAttachmentsForPageId( int $pageId ): array {
		return $this->db->getPageAttachmentsForPageId( $pageId );
	}

	/** @param int $blogPostId @return array */
	public function getBlogPostAttachmentsForBlogPostId( int $blogPostId ): array {
		return $this->db->getBlogPostAttachmentsForBlogPostId( $blogPostId );
	}

	/** @param int $templateId @return string|null */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		return $this->db->getTemplateTitleFromTemplateId( $templateId );
	}

	/** @param int $pageId @return string|null */
	public function getInvalidPageWikiTitleReason( int $pageId ): ?string {
		return $this->db->getInvalidPageWikiTitleReason( $pageId );
	}

	/** @param int $blogPostId @return string|null */
	public function getInvalidBlogPostWikiTitleReason( int $blogPostId ): ?string {
		return $this->db->getInvalidBlogPostWikiTitleReason( $blogPostId );
	}

	/** @param int $templateId @return string|null */
	public function getInvalidPageTemplateTitleReason( int $templateId ): ?string {
		return $this->db->getInvalidPageTemplateTitleReason( $templateId );
	}

	/** @param int $templateId @return int|null */
	public function getSpaceIdFromTemplateId( int $templateId ): ?int {
		return $this->db->getSpaceIdFromTemplateId( $templateId );
	}

	/** @param int $templateId @return string|null */
	public function getConfluencePageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		return $this->db->getConfluencePageTemplateTitleFromPageTemplateId( $templateId );
	}

	/** @param int $templateId @return string|null */
	public function getWikiPageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		return $this->db->getWikiPageTemplateTitleFromPageTemplateId( $templateId );
	}

	/** @param int $spaceDescriptionId @return bool */
	public function spaceDescriptionIdExists( int $spaceDescriptionId ): bool {
		return $this->db->spaceDescriptionIdExists( $spaceDescriptionId );
	}

	/** @param int $pageId @return bool */
	public function pageIdExists( int $pageId ): bool {
		return $this->db->pageIdExists( $pageId );
	}

	/** @param int $blogPostId @return bool */
	public function blogPostIdExists( int $blogPostId ): bool {
		return $this->db->blogPostIdExists( $blogPostId );
	}

	/** @param int $commentId @return bool */
	public function commentIdExists( int $commentId ): bool {
		return $this->db->commentIdExists( $commentId );
	}

	/** @param int $bodyContentId @return int|null */
	public function getContentIdForBodyContentId( int $bodyContentId ): ?int {
		return $this->db->getContentIdForBodyContentId( $bodyContentId );
	}

	/** @param int $descriptionId @return int|null */
	public function getSpaceIdForDescriptionId( int $descriptionId ): ?int {
		return $this->db->getSpaceIdForDescriptionId( $descriptionId );
	}

	/** @param int $spaceId @return int|null */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int {
		return $this->db->getSpaceHomepageIdForSpaceId( $spaceId );
	}

	/** @param int $pageId @return int|null */
	public function getSpaceIdForPageId( int $pageId ): ?int {
		return $this->db->getSpaceIdForPageId( $pageId );
	}

	/** @param int $blogPostId @return int|null */
	public function getSpaceIdForBlogPostId( int $blogPostId ): ?int {
		return $this->db->getSpaceIdForBlogPostId( $blogPostId );
	}

	/** @param int $pageId @return string|null */
	public function getConfluencePageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getConfluencePageTitleFromPageId( $pageId );
	}

	/** @param int $pageId @return string|null */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getWikiPageTitleFromPageId( $pageId );
	}

	/** @param int $blogPostId @return string|null */
	public function getConfluenceBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getConfluenceBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/** @param int $blogPostId @return string|null */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/** @param int $pageId @return array|null */
	public function getPageMetaByPageId( int $pageId ): ?array {
		return $this->db->getPageMetaByPageId( $pageId );
	}

	/** @param int $pageId @return array|null */
	public function getBlogPostMetaByPageId( int $pageId ): ?array {
		return $this->db->getBlogPostMetaByPageId( $pageId );
	}

	/** @param string $spaceKey @return string */
	public function getNamespaceFromSpaceKey( string $spaceKey ): string {
		$spacePrefix = $this->getSpacePrefixFromSpaceKey( $spaceKey );
		if ( $spacePrefix === '' ) {
			return '';
		}

		return $spacePrefix;
	}

	/**
	 * @param int $currentSpaceId
	 * @param int $targetSpaceId
	 * @param string $confluenceTitle
	 * @return string|null
	 */
	public function getWikiPageTitleForLink(
		int $currentSpaceId,
		int $targetSpaceId,
		string $confluenceTitle
	): ?string {
		$titles = $this->getPageTitlesFromSpaceId( $targetSpaceId, $confluenceTitle );
		if ( $titles === null ) {
			return null;
		}

		$wikiTitle = $titles['wiki_title'] ?? null;
		$interwikiTitle = $titles['interwiki_title'] ?? null;

		if ( $this->isSameWikiSpace( $currentSpaceId, $targetSpaceId ) ) {
			return $wikiTitle;
		}

		return $interwikiTitle ?: $wikiTitle;
	}

	/** @param string $attachmentTargetFileTitle @return string|null */
	public function getAttachmentContent( string $attachmentTargetFileTitle ): ?string {
		$reference = $this->getAttachmentReference( $attachmentTargetFileTitle );
		if ( $reference === null || !file_exists( $reference ) ) {
			return null;
		}
		$content = file_get_contents( $reference );
		if ( $content === false ) {
			return null;
		}

		return $content;
	}

	/** @param int $spaceId @return string|null */
	private function getWikiNameForSpaceId( int $spaceId ): ?string {
		$spaceKey = $this->getSpaceKeyFromSpaceId( $spaceId );
		if ( $spaceKey === null ) {
			return null;
		}

		return $this->getWikisConfigWikiNameForSpaceKey( $spaceKey );
	}

	/** @param int $currentSpaceId @param int $targetSpaceId @return bool */
	private function isSameWikiSpace( int $currentSpaceId, int $targetSpaceId ): bool {
		if ( $currentSpaceId === $targetSpaceId ) {
			return true;
		}

		$currentWiki = $this->getWikiNameForSpaceId( $currentSpaceId );
		$targetWiki = $this->getWikiNameForSpaceId( $targetSpaceId );

		if ( $currentWiki === null && $targetWiki === null ) {
			return true;
		}

		if ( $currentWiki === null || $targetWiki === null ) {
			return false;
		}

		return $currentWiki === $targetWiki;
	}
}
