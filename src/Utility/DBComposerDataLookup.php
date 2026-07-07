<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class DBComposerDataLookup {

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( private WorkspaceDB $workspaceDB ) {
	}

	/**
	 * @return array
	 */
	public function getSpaces(): array {
		return $this->workspaceDB->getSpaces();
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageIdWikiPageTitleMap( ?int $spaceId = null ): array {
		return $this->workspaceDB->getPageIdWikiPageTitleMap( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostIdWikiBlogPostTitleMap( ?int $spaceId = null ): array {
		return $this->workspaceDB->getBlogPostIdWikiBlogPostTitleMap( $spaceId );
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getPageRevisionsForPageId( int $pageId ): array {
		return $this->workspaceDB->getPageRevisionsForPageId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getBlogPostRevisionsForBlogPostId( int $pageId ): array {
		return $this->workspaceDB->getBlogPostRevisionsForBlogPostId( $pageId );
	}

	/**
	 * @param int $pageId
	 * @return int|null The space_id for the given page_id, or null if not found.
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int {
		return $this->workspaceDB->getSpaceIdForPageId( $pageId );
	}

	/**
	 * @param int $spaceId
	 * @return int|null The page_id of the space homepage for the given space_id, or null if not found.
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int {
		return $this->workspaceDB->getSpaceHomepageIdForSpaceId( $spaceId );
	}

	/**
	 *
	 * @param int $spaceId
	 * @return array
	 */
	public function getSpaceDescriptionRevisionsForSpaceId( int $spaceId ): array {
		return $this->workspaceDB->getSpaceDescriptionRevisionsForSpaceId( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForPages( ?int $spaceId = null ): array {
		return $this->workspaceDB->getCommentsForPages( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForBlogPosts( ?int $spaceId = null ): array {
		return $this->workspaceDB->getCommentsForBlogPosts( $spaceId );
	}

	/**
	 * @return array
	 */
	public function getUsers(): array {
		return $this->workspaceDB->getUsers();
	}

	/**
	 * @param string $userKey
	 * @return string|null
	 */
	public function getUsernameFromUserKey( string $userKey ): ?string {
		return $this->workspaceDB->getUsernameFromUserKey( $userKey );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageAttachments( ?int $spaceId = null ): array {
		return $this->workspaceDB->getPageAttachments( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostAttachments( ?int $spaceId = null ): array {
		return $this->workspaceDB->getBlogPostAttachments( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getAdditionalAttachments( ?int $spaceId = null ): array {
		return $this->workspaceDB->getAdditionalAttachments( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageTemplateIdWikiTitleMap( ?int $spaceId = null ): array {
		return $this->workspaceDB->getPageTemplateIdWikiTitleMap( $spaceId );
	}

	/**
	 * @param int $templateId
	 * @return array
	 */
	public function getPageTemplateRevisionsForTemplateId( int $templateId ): array {
		return $this->workspaceDB->getPageTemplateRevisionsForTemplateId( $templateId );
	}

	/**
	 * Get the wiki page title for a given page ID.
	 * If the page has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		return $this->workspaceDB->getWikiPageTitleFromPageId( $pageId );
	}

	/**
	 * Returns the talk page wiki title for comments on the given page.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageCommentTitleFromPageId( int $pageId ): ?string {
		return $this->workspaceDB->getWikiPageCommentTitleFromPageId( $pageId );
	}

	/**
	 * Get the wiki blog_post title for a given blog_post ID.
	 * If the blog_post has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		return $this->workspaceDB->getWikiBlogPostTitleFromBlogPostId( $blogPostId );
	}

	/**
	 * Returns the talk page wiki title for comments on the given blog post.
	 *
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostCommentsFromBlogPostId( int $blogPostId ): ?string {
		return $this->workspaceDB->getWikiBlogPostCommentTitleFromBlogPostId( $blogPostId );
	}

	/**
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachmentRevisionsForAttachmentId( int $attachmentId ): array {
		return $this->workspaceDB->getAttachmentRevisionsForAttachmentId( $attachmentId );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageInvalid( string $wikiTitle ): bool {
		return $this->workspaceDB->isPageInvalid( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isBlogPostInvalid( string $wikiTitle ): bool {
		return $this->workspaceDB->isBlogPostInvalid( $wikiTitle );
	}

	/**
	 * @param int $attachmentId
	 * @return bool
	 */
	public function isAttachmentInvalid( int $attachmentId ): bool {
		return $this->workspaceDB->isAttachmentInvalid( $attachmentId );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageTemplateInvalid( string $wikiTitle ): bool {
		return $this->workspaceDB->isPageTemplateInvalid( $wikiTitle );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidPages( ?int $spaceId = null ): array {
		return $this->workspaceDB->getInvalidPages( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidBlogPosts( ?int $spaceId = null ): array {
		return $this->workspaceDB->getInvalidBlogPosts( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidAttachments( ?int $spaceId = null ): array {
		return $this->workspaceDB->getInvalidAttachments( $spaceId );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getInvalidPageTemplates( ?int $spaceId = null ): array {
		return $this->workspaceDB->getInvalidPageTemplates( $spaceId );
	}

	/**
	 * @param int|null $spaceId If given, only return pages for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title', 'parent_page_id', 'position']
	 */
	public function getPagesForSidebar( ?int $spaceId = null ): array {
		return $this->workspaceDB->getPagesForSidebar( $spaceId );
	}

	/**
	 * @param int|null $spaceId If given, only return blog posts for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title']
	 */
	public function getBlogPostsForSidebar( ?int $spaceId = null ): array {
		return $this->workspaceDB->getBlogPostsForSidebar( $spaceId );
	}

	/**
	 * @param int $attachmentId
	 * @return string
	 */
	public function getAttachmentDescription( int $attachmentId ): string {
		return $this->workspaceDB->getAttachmentDescription( $attachmentId );
	}
}
