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
	public function getPageIdTargetWikiTitleMap(): array {
		return $this->workspaceDB->getPageIdTargetWikiTitleMap();
	}

	/**
	 * @return array
	 */
	public function getBlogPostIdTargetBlogPostTitleMap(): array {
		return $this->workspaceDB->getBlogPostIdTargetBlogPostTitleMap();
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
	 * @return int
	 */
	public function getSpaceIdForPageId( int $pageId ): int {
		return $this->workspaceDB->getSpaceIdForPageId( $pageId );
	}

	/**
	 * @param int $spaceId
	 * @return int
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): int {
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
	public function getUsers(): array {
		return $this->workspaceDB->getUsers();
	}

	/**
	 * @param string $userKey
	 * @return string
	 */
	public function getUsernameFromUserKey( string $userKey ): string {
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
	public function getAdditionalAttachments(): array {
		return $this->workspaceDB->getAdditionalAttachments();
	}

	/**
	 * @return array
	 */
	public function getPageTemplateIdTargetTitleMap(): array {
		return $this->workspaceDB->getPageTemplateIdTargetTitleMap();
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
	 * Get the target page title for a given page ID.
	 * If the page has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $pageId
	 * @return string
	 */
	public function getTargetPageTitleFromPageId( int $pageId ): string {
		return $this->workspaceDB->getTargetPageTitleFromPageId( $pageId );
	}

	/**
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachmentRevisionsForAttachmentId( int $attachmentId ): array {
		return $this->workspaceDB->getAttachmentRevisionsForAttachmentId( $attachmentId );
	}
}
