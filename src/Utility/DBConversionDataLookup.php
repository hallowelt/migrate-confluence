<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Exception;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class DBConversionDataLookup {

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( private WorkspaceDB $workspaceDB ) {
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
	public function getSpaceIdToPrefixMap(): array {
		return $this->workspaceDB->getMapSpaceIdToPrefix();
	}

	/**
	 * @param string $spaceKey
	 *
	 * @return int
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): int {
		// See src/Analyzer/Processor/Spaces
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}

		return $this->workspaceDB->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * Get the mediawiki namespace for a given space key.
	 * If key is not found return the space key itself as namespace prefix.
	 *
	 * @param string $spaceKey
	 *
	 * @return string
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): string {
		return $this->workspaceDB->getSpacePrefixFromSpaceKey( $spaceKey );
	}

	/**
	 * Get the mediawiki namespace for a given space key.
	 * If key is not found return the space key itself as namespace prefix.
	 *
	 * @param string $spaceKey
	 *
	 * @return string
	 */
	public function getNamepspaceFromSpaceKey( string $spaceKey ): string {
		$spacePrefix = $this->workspaceDB->getSpacePrefixFromSpaceKey( $spaceKey );
		if ( $spacePrefix === '' ) {
			return '';
		}
		$namespace = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) );
		if ( $namespace === false ) {
			return $spacePrefix;
		}
		return $namespace;
	}

	/**
	 * Get the wiki page title for a given space key.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public function getTargetWikiPageTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->workspaceDB->getTargetWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * Get the wikit file title for a given space key, confluence title and original attachment filename.
	 * If no entry is found, return the original attachment filename as title
	 * and mark it as broken link (isBroken = true) in the returned array.
	 *
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return array
	 */
	public function getTargetFileTitleFromSpaceKey(
		string $spaceKey, string $confluenceTitle, string $originalAttachmentFilename
	): array {
		return $this->workspaceDB->getTargetFileTitleFromSpaceKey(
			$spaceKey, $confluenceTitle, $originalAttachmentFilename
		);
	}

	/**
	 * Get the wikit file title for a given space key, confluence title and original attachment filename.
	 * If no entry is found, return the original attachment filename as title
	 * and mark it as broken link (isBroken = true) in the returned array.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string
	 */
	public function getTargetFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): string {
		return $this->workspaceDB->getTargetFileTitleFromSpaceId(
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
		return $this->workspaceDB->getAttachmentMetadataForPage( $spaceId, $rawPageTitle );
	}

	/**
	 * @param string $attachmentTargetFileTitle
	 * @return string|null
	 */
	public function getAttachmentContent( string $attachmentTargetFileTitle ): ?string {
		$reference = $this->workspaceDB->getAttachmentReference( $attachmentTargetFileTitle );
		if ( $reference === null || !file_exists( $reference ) ) {
			return null;
		}
		$content = file_get_contents( $reference );
		if ( !$content ) {
			return null;
		}
		return $content;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getTargetFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		return $this->workspaceDB->getTargetFileTitlesForPage( $spaceId, $rawPageTitle );
	}

	/**
	 * @return array
	 */
	public function getPageAttachmentsForPageId( int $pageId ): array {
		return $this->workspaceDB->getPageAttachmentsForPageId( $pageId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		return $this->workspaceDB->getTemplateTitleFromTemplateId( $templateId );
	}
}
