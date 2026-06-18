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
	public function getPageIdWikiPageTitleMap(): array {
		return $this->workspaceDB->getPageIdWikiPageTitleMap();
	}

	/**
	 * @return array
	 */
	public function getBlogPostIdWikiBlogPostTitleMap(): array {
		return $this->workspaceDB->getBlogPostIdWikiBlogPostTitleMap();
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
	 * @return array
	 */
	public function getCommentsForPages(): array {
		return $this->workspaceDB->getCommentsForPages();
	}

	/**
	 * @return array
	 */
	public function getCommentsForBlogPosts(): array {
		return $this->workspaceDB->getCommentsForBlogPosts();
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
	 * @return array
	 */
	public function getPageAttachments(): array {
		return $this->workspaceDB->getPageAttachments();
	}

	/**
	 * @return array
	 */
	public function getBlogPostAttachments(): array {
		return $this->workspaceDB->getBlogPostAttachments();
	}

	/**
	 * @return array
	 */
	public function getAdditionalAttachments(): array {
		return $this->workspaceDB->getAdditionalAttachments();
	}

	/**
	 * @return array
	 */
	public function getPageTemplateIdWikiTitleMap(): array {
		return $this->workspaceDB->getPageTemplateIdWikiTitleMap();
	}

	/**
	 * @param int $templateId
	 * @return array
	 */
	public function getPageTemplateRevisionsForTemplateId( int $templateId ): array {
		return $this->workspaceDB->getPageTemplateRevisionsForTemplateId( $templateId );
	}

	/**
	 * @param int $templateId
	 * @return int
	 */
	public function getSpaceIdForTemplateId( int $templateId ): int {
		return $this->workspaceDB->getSpaceIdFromTemplateId( $templateId ) ?? 0;
	}

	/**
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachment( int $attachmentId ): array {
		return $this->workspaceDB->getAttachment( $attachmentId );
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
	 * @param int $blogPostId
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

	public function getInvalidPages(): array {
		return $this->workspaceDB->getInvalidPages();
	}

	public function getInvalidBlogPosts(): array {
		return $this->workspaceDB->getInvalidBlogPosts();
	}

	public function getInvalidAttachments(): array {
		return $this->workspaceDB->getInvalidAttachments();
	}

	public function getInvalidPageTemplates(): array {
		return $this->workspaceDB->getInvalidPageTemplates();
	}
}
