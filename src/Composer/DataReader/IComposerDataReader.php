<?php

namespace HalloWelt\MigrateConfluence\Composer\DataReader;

interface IComposerDataReader {

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
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageIdWikiPageTitleMap( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostIdWikiBlogPostTitleMap( ?int $spaceId = null ): array;

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getPageRevisionsForPageId( int $pageId ): array;

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getBlogPostRevisionsForBlogPostId( int $pageId ): array;

	/**
	 * @param int $pageId
	 * @return int|null The space_id for the given page_id, or null if not found.
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int;

	/**
	 * @param int $spaceId
	 * @return int|null The page_id of the space homepage for the given space_id, or null if not found.
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int;

	/**
	 *
	 * @param int $spaceId
	 * @return array
	 */
	public function getSpaceDescriptionRevisionsForSpaceId( int $spaceId ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForPages( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForBlogPosts( ?int $spaceId = null ): array;

	/**
	 * @return array
	 */
	public function getUsers(): array;

	/**
	 * @param string $userKey
	 * @return string|null
	 */
	public function getUsernameFromUserKey( string $userKey ): ?string;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageAttachments( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostAttachments( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getAdditionalAttachments( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageTemplateIdWikiTitleMap( ?int $spaceId = null ): array;

	/**
	 * @param int $templateId
	 * @return array
	 */
	public function getPageTemplateRevisionsForTemplateId( int $templateId ): array;

	/**
	 * Get the wiki page title for a given page ID.
	 * If the page has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string;

	/**
	 * Returns the talk page wiki title for comments on the given page.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageCommentTitleFromPageId( int $pageId ): ?string;

	/**
	 * Get the wiki blog_post title for a given blog_post ID.
	 * If the blog_post has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string;

	/**
	 * Returns the talk page wiki title for comments on the given blog post.
	 *
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostCommentsFromBlogPostId( int $blogPostId ): ?string;

	/**
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachmentRevisionsForAttachmentId( int $attachmentId ): array;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageInvalid( string $wikiTitle ): bool;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isBlogPostInvalid( string $wikiTitle ): bool;

	/**
	 * @param int $attachmentId
	 * @return bool
	 */
	public function isAttachmentInvalid( int $attachmentId ): bool;

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageTemplateInvalid( string $wikiTitle ): bool;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidPages( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidBlogPosts( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidAttachments( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidPageTemplates( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId If given, only return pages for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title', 'parent_page_id', 'position']
	 */
	public function getPagesForSidebar( ?int $spaceId = null ): array;

	/**
	 * @param int|null $spaceId If given, only return blog posts for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title']
	 */
	public function getBlogPostsForSidebar( ?int $spaceId = null ): array;

	/**
	 * @param int $attachmentId
	 * @return string
	 */
	public function getAttachmentDescription( int $attachmentId ): string;

	/**
	 * @return array
	 */
	public function getWikisConfigWikiNames(): array;

	public function getWikisConfigSpacesForWikiName( string $wikiName ): array;

	/**
	 * @return string[] list of required template names
	 */
	public function getRequiredTemplates(): array;
}
