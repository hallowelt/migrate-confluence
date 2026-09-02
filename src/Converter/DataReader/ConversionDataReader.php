<?php

namespace HalloWelt\MigrateConfluence\Converter\DataReader;

use Exception;
use HalloWelt\MigrateConfluence\Database\DataReader\AbstractDataReader;

class ConversionDataReader extends AbstractDataReader {

	public function getUsernameFromUserKey( string $userKey ): ?string {
		return $this->db->getUsernameFromUserKey( $userKey );
	}

	/**
	 * @return array|null The properties of the given page or null, if an error occurred
	 */
	public function getPropertiesForPageId( int $pageId ): ?array {
		return $this->db->getPropertiesForPageId( $pageId );
	}

	public function getSpaceIdToPrefixMap(): array {
		return $this->db->getMapSpaceIdToPrefix();
	}

	public function getSpaceIdFromSpaceKey( string $spaceKey ): ?int {
		// See src/Analyzer/Processor/Spaces
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}

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
	 * Get the mediawiki namespace for a given space key.
	 * If key is not found return the space key itself as namespace prefix.
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): string {
		return $this->db->getSpacePrefixFromSpaceKey( $spaceKey );
	}

	/**
	 * Get the mediawiki namespace for a given space key.
	 * If key is not found return the space key itself as namespace prefix.
	 */
	public function getNamespaceFromSpaceKey( string $spaceKey ): string {
		$spacePrefix = $this->getSpacePrefixFromSpaceKey( $spaceKey );
		if ( $spacePrefix === '' ) {
			return '';
		}
		return $spacePrefix;
	}

	public function getSpaceMainPageWikiTitleForSpaceId( int $spaceId ): ?string {
		return $this->db->getSpaceMainPageWikiTitleForSpaceId( $spaceId );
	}

	/**
	 * Get the wiki page title for a given space key.
	 */
	public function getWikiPageTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->db->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * Resolve a page title for links based on wiki grouping:
	 * - same wiki: use wiki_title
	 * - different wiki: use interwiki_title
	 * - if no wiki config exists: treat all spaces as same wiki
	 */
	public function getWikiPageTitleForLink(
		int $currentSpaceId,
		int $targetSpaceId,
		string $confluenceTitle
	): ?string {
		$titles = $this->db->getPageTitlesFromSpaceId( $targetSpaceId, $confluenceTitle );
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

	private function getWikiNameForSpaceId( int $spaceId ): ?string {
		$spaceKey = $this->db->getSpaceKeyFromSpaceId( $spaceId );
		if ( $spaceKey === null ) {
			return null;
		}

		return $this->db->getWikisConfigWikiNameForSpaceKey( $spaceKey );
	}

	private function isSameWikiSpace( int $currentSpaceId, int $targetSpaceId ): bool {
		if ( $currentSpaceId === $targetSpaceId ) {
			return true;
		}

		$currentWiki = $this->getWikiNameForSpaceId( $currentSpaceId );
		$targetWiki = $this->getWikiNameForSpaceId( $targetSpaceId );

		if ( $currentWiki === null && $targetWiki === null ) {
			// No wiki config present: all spaces are treated as one wiki.
			return true;
		}

		if ( $currentWiki === null || $targetWiki === null ) {
			return false;
		}

		return $currentWiki === $targetWiki;
	}

	/**
	 * Get the wiki blog post title for a given space key.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public function getWikiBlogPostTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->db->getWikiBlogPostTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * Get the wiki file title for a given space key, confluence title and original attachment filename.
	 * If no entry is found, return the original attachment filename as title
	 * and mark it as broken link (isBroken = true) in the returned array.
	 *
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

	/**
	 * Returns target file titles with their full metadata for all attachments on a page.
	 * The returned array is keyed by confluence file key. Each value contains 'targetTitle'
	 * plus any additional metadata fields (e.g. 'labels', 'mediaType', etc.).
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getAttachmentMetadataForPage(
		int $spaceId, string $rawPageTitle
	): array {
		return $this->db->getAttachmentMetadataForPage( $spaceId, $rawPageTitle );
	}

	/**
	 * Returns target file titles with their full metadata for all attachments on a blog post.
	 * The returned array is keyed by confluence file key. Each value contains 'targetTitle'
	 * plus any additional metadata fields (e.g. 'labels', 'mediaType', etc.).
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getAttachmentMetadataForBlogPost(
		int $spaceId, string $rawPageTitle
	): array {
		return $this->db->getAttachmentMetadataForBlogPost( $spaceId, $rawPageTitle );
	}

	/**
	 * @param string $attachmentTargetFileTitle
	 * @return string|null
	 */
	public function getAttachmentContent( string $attachmentTargetFileTitle ): ?string {
		$reference = $this->db->getAttachmentReference( $attachmentTargetFileTitle );
		if ( $reference === null || !file_exists( $reference ) ) {
			return null;
		}
		$content = file_get_contents( $reference );
		if ( $content === false ) {
			return null;
		}
		return $content;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getWikiFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getWikiFileTitlesForPage( $spaceId, $rawPageTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getWikiFileTitlesForBlogPost( int $spaceId, string $rawPageTitle ): array {
		return $this->db->getWikiFileTitlesForBlogPost( $spaceId, $rawPageTitle );
	}

	/**
	 * @param int $pageId
	 *
	 * @return array
	 */
	public function getPageAttachmentsForPageId( int $pageId ): array {
		return $this->db->getPageAttachmentsForPageId( $pageId );
	}

	/**
	 * @param int $blogPostId
	 *
	 * @return array
	 */
	public function getBlogPostAttachmentsForBlogPostId( int $blogPostId ): array {
		return $this->db->getBlogPostAttachmentsForBlogPostId( $blogPostId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		return $this->db->getTemplateTitleFromTemplateId( $templateId );
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	public function getInvalidPageWikiTitleReason( int $pageId ): ?string {
		return $this->db->getInvalidPageWikiTitleReason( $pageId );
	}

	/**
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getInvalidBlogPostWikiTitleReason( int $blogPostId ): ?string {
		return $this->db->getInvalidBlogPostWikiTitleReason( $blogPostId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getInvalidPageTemplateTitleReason( int $templateId ): ?string {
		return $this->db->getInvalidPageTemplateTitleReason( $templateId );
	}

	public function getPageByWikiTitle( string $wikiTitle ): ?array {
		return $this->db->getPageByWikiTitle( $wikiTitle );
	}

	public function getConfluencePageBodyContent( array $bodyContentIds ): ?string {
		$bodyContent = "";
		foreach ( $bodyContentIds as $bodyContentId ) {
			$body = $this->db->getBodyContentBodyByBodyContentId( $bodyContentId );

			if ( $body ) {
				$bodyContent .= $body;
			}
		}

		if ( empty( $bodyContent ) ) {
			return null;
		}

		return $body;
	}

	/**
	 * @param int $templateId
	 * @return int|null
	 */
	public function getSpaceIdFromTemplateId( int $templateId ): ?int {
		return $this->db->getSpaceIdFromTemplateId( $templateId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getConfluencePageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		return $this->db->getConfluencePageTemplateTitleFromPageTemplateId( $templateId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getWikiPageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		return $this->db->getWikiPageTemplateTitleFromPageTemplateId( $templateId );
	}

	/**
	 * @param int $spaceDescriptionId
	 * @return bool
	 */
	public function spaceDescriptionIdExists( int $spaceDescriptionId ): bool {
		return $this->db->spaceDescriptionIdExists( $spaceDescriptionId );
	}

	/**
	 * @param int $pageId
	 * @return string|null
	 */
	public function getConfluencePageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getConfluencePageTitleFromPageId( $pageId );
	}

	/**
	 * Get the wiki page title for a given page ID.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		return $this->db->getWikiPageTitleFromPageId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return bool
	 */
	public function pageIdExists( int $pageId ): bool {
		return $this->db->pageIdExists( $pageId );
	}

	/**
	 * @param int $blogPostId
	 * @return bool
	 */
	public function blogPostIdExists( int $blogPostId ): bool {
		return $this->db->blogPostIdExists( $blogPostId );
	}

	/**
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getConfluenceBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getConfluenceBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/**
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->db->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/**
	 * @param int $commentId
	 * @return bool
	 */
	public function commentIdExists( int $commentId ): bool {
		return $this->db->commentIdExists( $commentId );
	}

	/**
	 * @param int $bodyContentId
	 * @return int|null
	 */
	public function getContentIdForBodyContentId( int $bodyContentId ): ?int {
		return $this->db->getContentIdForBodyContentId( $bodyContentId );
	}

	/**
	 * @param int $descriptionId
	 * @return int|null
	 */
	public function getSpaceIdForDescriptionId( int $descriptionId ): ?int {
		return $this->db->getSpaceIdForDescriptionId( $descriptionId );
	}

	/**
	 * @param int $spaceId
	 * @return int|null
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int {
		return $this->db->getSpaceHomepageIdForSpaceId( $spaceId );
	}

	/**
	 * @param int $pageId
	 * @return int|null
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int {
		return $this->db->getSpaceIdForPageId( $pageId );
	}

	/**
	 * @param int $blogPostId
	 * @return int|null
	 */
	public function getSpaceIdForBlogPostId( int $blogPostId ): ?int {
		return $this->db->getSpaceIdForBlogPostId( $blogPostId );
	}

	/**
	 * @param int $pageId
	 * @return array|null
	 */
	public function getPageMetaByPageId( int $pageId ): ?array {
		return $this->db->getPageMetaByPageId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return array|null
	 */
	public function getBlogPostMetaByPageId( int $pageId ): ?array {
		return $this->db->getBlogPostMetaByPageId( $pageId );
	}
}
