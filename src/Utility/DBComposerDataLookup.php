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
	public function getPageIdTargetPageTitleMap(): array {
		return $this->workspaceDB->getPageIdTargetPageTitleMap();
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
	public function getBlogPostRevisionsForPageId( int $pageId ): array {
		return $this->workspaceDB->getBlogPostRevisionsForPageId( $pageId );
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
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachment( int $attachmentId ): array {
		return $this->workspaceDB->getAttachment( $attachmentId );
	}
}
