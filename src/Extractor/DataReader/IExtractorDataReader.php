<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataReader;

use HalloWelt\MediaWiki\Lib\Migration\Database\DataReader\IDataReader;

/**
 * Read side of the workspace database for the extract step.
 *
 * Only the queries the extract processors actually run are exposed here.
 */
interface IExtractorDataReader extends IDataReader {

	/**
	 * @param string $step
	 * @param string $type
	 *
	 * @return array
	 */
	public function getLogEntriesForStep( string $step, string $type = '' ): array;

	/**
	 * @return array
	 */
	public function getSpaces(): array;

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array;

	/**
	 * @return array
	 */
	public function getMapSpaceIdToKey(): array;

	/**
	 * @return array
	 */
	public function getMapSpaceIdToHomepageId(): array;

	/**
	 * @param int $spaceId
	 *
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string;

	/**
	 * @return array
	 */
	public function getSpaceDescriptions(): array;

	/**
	 * @return array
	 */
	public function getCurrentSpaceDescriptions(): array;

	/**
	 * @return array
	 */
	public function getPages(): array;

	/**
	 * @return array
	 */
	public function getCurrentPages(): array;

	/**
	 * @return array
	 */
	public function getMapPageIdtoParentPageId(): array;

	/**
	 * @return array
	 */
	public function getMapPageIdToConfluenceTitle(): array;

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string;

	/**
	 * @param int $pageId
	 *
	 * @return int|null
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int;

	/**
	 * @return array
	 */
	public function getInvalidPageWikiTitles(): array;

	/**
	 * @return array
	 */
	public function getBlogPosts(): array;

	/**
	 * @return array
	 */
	public function getCurrentBlogPosts(): array;

	/**
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string;

	/**
	 * @return array
	 */
	public function getInvalidBlogPostWikiTitles(): array;

	/**
	 * @param int $contentId
	 *
	 * @return array
	 */
	public function getBodyContentIdsForContentId( int $contentId ): array;

	/**
	 * @param int $bodyContentId
	 *
	 * @return string|null
	 */
	public function getBodyContentBodyByBodyContentId( int $bodyContentId ): ?string;

	/**
	 * @return array
	 */
	public function getAttachments(): array;

	/**
	 * @return array
	 */
	public function getCurrentAttachments(): array;

	/**
	 * @return array
	 */
	public function getPageAttachments(): array;

	/**
	 * @return array
	 */
	public function getBlogPostAttachments(): array;

	/**
	 * @return array
	 */
	public function getAdditionalAttachments(): array;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkPageAttachmentWikiTitleExists( string $wikiTitle ): bool;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkBlogPostAttachmentWikiTitleExists( string $wikiTitle ): bool;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function checkAdditionalAttachmentWikiTitleExists( string $wikiTitle ): bool;

	/**
	 * @param int $attachmentId
	 *
	 * @return array
	 */
	public function getAttachmentMetaById( int $attachmentId ): array;

	/**
	 * @return array
	 */
	public function getInvalidAttachmentTitles(): array;

	/**
	 * @return array
	 */
	public function getComments(): array;

	/**
	 * @return array
	 */
	public function getCurrentComments(): array;

	/**
	 * @return array
	 */
	public function getCommentsForPages(): array;

	/**
	 * @return array
	 */
	public function getCommentsForBlogPosts(): array;

	/**
	 * @param int $labellingId
	 *
	 * @return array|null
	 */
	public function getLabellingById( int $labellingId ): ?array;

	/**
	 * @param int $labelId
	 *
	 * @return array|null
	 */
	public function getLabelById( int $labelId ): ?array;

	/**
	 * @return array
	 */
	public function getPageTemplates(): array;

	/**
	 * @return array
	 */
	public function getCurrentPageTemplateContents(): array;
}
