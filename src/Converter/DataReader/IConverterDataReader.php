<?php

namespace HalloWelt\MigrateConfluence\Converter\DataReader;

use HalloWelt\MediaWiki\Lib\Migration\Database\DataReader\IDataReader;

/**
 * Read side of the workspace database for the convert step.
 */
interface IConverterDataReader extends IDataReader {

	/**
	 * @param string $userKey
	 *
	 * @return string|null
	 */
	public function getUsernameFromUserKey( string $userKey ): ?string;

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array;

	/**
	 * @param int $pageId
	 *
	 * @return array|null
	 */
	public function getPropertiesForPageId( int $pageId ): ?array;

	/**
	 * @param string $spaceKey
	 *
	 * @return int|null
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): ?int;

	/**
	 * @param int $spaceId
	 *
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string;

	/**
	 * @param string $spaceKey
	 *
	 * @return string
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): string;

	/**
	 * @param int $spaceId
	 *
	 * @return string|null
	 */
	public function getSpaceMainPageWikiTitleForSpaceId( int $spaceId ): ?string;

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string;

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return array|null
	 */
	public function getPageTitlesFromSpaceId( int $spaceId, string $confluenceTitle ): ?array;

	/**
	 * @param string $spaceKey
	 *
	 * @return string|null
	 */
	public function getWikisConfigWikiNameForSpaceKey( string $spaceKey ): ?string;

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string;

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 *
	 * @return string|null
	 */
	public function getWikiFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): ?string;

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return array
	 */
	public function getAttachmentMetadataForPage( int $spaceId, string $rawPageTitle ): array;

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return array
	 */
	public function getAttachmentMetadataForBlogPost( int $spaceId, string $rawPageTitle ): array;

	/**
	 * @param string $attachmentTargetFileTitle
	 *
	 * @return string|null
	 */
	public function getAttachmentReference( string $attachmentTargetFileTitle ): ?string;

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return array
	 */
	public function getWikiFileTitlesForPage( int $spaceId, string $rawPageTitle ): array;

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return array
	 */
	public function getWikiFileTitlesForBlogPost( int $spaceId, string $rawPageTitle ): array;

	/**
	 * @param int $pageId
	 *
	 * @return array
	 */
	public function getPageAttachmentsForPageId( int $pageId ): array;

	/**
	 * @param int $blogPostId
	 *
	 * @return array
	 */
	public function getBlogPostAttachmentsForBlogPostId( int $blogPostId ): array;

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string;

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getInvalidPageWikiTitleReason( int $pageId ): ?string;

	/**
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getInvalidBlogPostWikiTitleReason( int $blogPostId ): ?string;

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getInvalidPageTemplateTitleReason( int $templateId ): ?string;

	/**
	 * @param int $templateId
	 *
	 * @return int|null
	 */
	public function getSpaceIdFromTemplateId( int $templateId ): ?int;

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getConfluencePageTemplateTitleFromPageTemplateId( int $templateId ): ?string;

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getWikiPageTemplateTitleFromPageTemplateId( int $templateId ): ?string;

	/**
	 * @param int $spaceDescriptionId
	 *
	 * @return bool
	 */
	public function spaceDescriptionIdExists( int $spaceDescriptionId ): bool;

	/**
	 * @param int $pageId
	 *
	 * @return bool
	 */
	public function pageIdExists( int $pageId ): bool;

	/**
	 * @param int $blogPostId
	 *
	 * @return bool
	 */
	public function blogPostIdExists( int $blogPostId ): bool;

	/**
	 * @param int $commentId
	 *
	 * @return bool
	 */
	public function commentIdExists( int $commentId ): bool;

	/**
	 * @param int $bodyContentId
	 *
	 * @return int|null
	 */
	public function getContentIdForBodyContentId( int $bodyContentId ): ?int;

	/**
	 * @param int $descriptionId
	 *
	 * @return int|null
	 */
	public function getSpaceIdForDescriptionId( int $descriptionId ): ?int;

	/**
	 * @param int $spaceId
	 *
	 * @return int|null
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int;

	/**
	 * @param int $pageId
	 *
	 * @return int|null
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int;

	/**
	 * @param int $blogPostId
	 *
	 * @return int|null
	 */
	public function getSpaceIdForBlogPostId( int $blogPostId ): ?int;

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getConfluencePageTitleFromPageId( int $pageId ): ?string;

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string;

	/**
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getConfluenceBlogPostTitleFromBlogPostId( int $blogPostId ): ?string;

	/**
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string;

	/**
	 * @param int $pageId
	 *
	 * @return array|null
	 */
	public function getPageMetaByPageId( int $pageId ): ?array;

	/**
	 * @param int $pageId
	 *
	 * @return array|null
	 */
	public function getBlogPostMetaByPageId( int $pageId ): ?array;

	/**
	 * @param string $spaceKey
	 *
	 * @return string
	 */
	public function getNamespaceFromSpaceKey( string $spaceKey ): string;

	/**
	 * Resolve a page title for links based on wiki grouping.
	 *
	 * @param int $currentSpaceId
	 * @param int $targetSpaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleForLink(
		int $currentSpaceId,
		int $targetSpaceId,
		string $confluenceTitle
	): ?string;

	/**
	 * @param string $attachmentTargetFileTitle
	 *
	 * @return string|null
	 */
	public function getAttachmentContent( string $attachmentTargetFileTitle ): ?string;

	/**
	 * @param string $wikiTitle
	 *
	 * @return array|null
	 */
	public function getPageByWikiTitle( string $wikiTitle ): ?array;

	/**
	 * @param array $bodyContentIds
	 *
	 * @return string|null
	 */
	public function getConfluencePageBodyContent( array $bodyContentIds ): ?string;
}
