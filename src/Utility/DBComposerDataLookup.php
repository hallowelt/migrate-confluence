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

	public function getPageRevisionsForPageId( int $pageId ): array {
		return $this->workspaceDB->getPageRevisionsForPageId( $pageId );
	}

	public function getSpaceIdForPageId( int $pageId ): int {
		return $this->workspaceDB->getSpaceIdForPageId( $pageId );
	}

	public function getSpaceHomepageIdForSpaceId( int $spaceId ): int {
		return $this->workspaceDB->getSpaceHomepageIdForSpaceId( $spaceId );
	}

	public function getSpaceDescriptionRevisionsForSpaceId( int $spaceId ): array {
		return $this->workspaceDB->getSpaceDescriptionRevisionsForSpaceId( $spaceId );
	}

	public function getCommentsForPages(): array {
		return $this->workspaceDB->getCommentsForPages();
	}

	public function getUsers(): array {
		return $this->workspaceDB->getUsers();
	}

	public function getPageAttachments(): array {
		return $this->workspaceDB->getPageAttachments();
	}

	public function getAdditionalAttachments(): array {
		return $this->workspaceDB->getAdditionalAttachments();
	}

	public function getAttachment( int $attachmentId ): array {
		return $this->workspaceDB->getAttachment( $attachmentId );
	}
}
