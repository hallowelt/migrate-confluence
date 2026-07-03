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
	 * @return string|null
	 */
	public function getUsernameFromUserKey( string $userKey ): ?string {
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
	 * @return int|null
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): ?int {
		// See src/Analyzer/Processor/Spaces
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}

		return $this->workspaceDB->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string {
		return $this->workspaceDB->getSpaceKeyFromSpaceId( $spaceId );
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
	public function getNamespaceFromSpaceKey( string $spaceKey ): string {
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
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceMainPageWikiTitleForSpaceId( int $spaceId ): ?string {
		return $this->workspaceDB->getSpaceMainPageWikiTitleForSpaceId( $spaceId );
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
	public function getWikiPageTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->workspaceDB->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
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
		return $this->workspaceDB->getWikiBlogPostTitleFromSpaceId( $spaceId, $confluenceTitle );
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
		return $this->workspaceDB->getWikiFileTitleFromSpaceId(
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
		return $this->workspaceDB->getAttachmentMetadataForBlogPost( $spaceId, $rawPageTitle );
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
		return $this->workspaceDB->getWikiFileTitlesForPage( $spaceId, $rawPageTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array
	 */
	public function getWikiFileTitlesForBlogPost( int $spaceId, string $rawPageTitle ): array {
		return $this->workspaceDB->getWikiFileTitlesForBlogPost( $spaceId, $rawPageTitle );
	}

	/**
	 * @return array
	 */
	public function getPageAttachmentsForPageId( int $pageId ): array {
		return $this->workspaceDB->getPageAttachmentsForPageId( $pageId );
	}

	/**
	 * @return array
	 */
	public function getBlogPostAttachmentsForBlogPostId( int $blogPostId ): array {
		return $this->workspaceDB->getBlogPostAttachmentsForBlogPostId( $blogPostId );
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		return $this->workspaceDB->getTemplateTitleFromTemplateId( $templateId );
	}

	/**
	 * Get the interwiki page title for a page in a given space.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return string|null
	 */
	public function getInterwikiPageTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->workspaceDB->getInterwikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * Get the interwiki blog post title for a blog post in a given space.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return string|null
	 */
	public function getInterwikiBlogPostTitleFromSpaceId(
		int $spaceId, string $confluenceTitle
	): ?string {
		return $this->workspaceDB->getInterwikiBlogPostTitleFromSpaceId( $spaceId, $confluenceTitle );
	}

	/**
	 * Get the appropriate page title for a link based on wiki comparison.
	 * If the current space and target space belong to the same wiki (per WikiConfig),
	 * returns the wiki_title. Otherwise returns the interwiki_title.
	 *
	 * @param int $currentSpaceId
	 * @param int $targetSpaceId
	 * @param string $confluenceTitle
	 * @param WikiConfig $wikiConfig
	 * @return string|null
	 */
	public function getPageTitleForLink(
		int $currentSpaceId, int $targetSpaceId, string $confluenceTitle, WikiConfig $wikiConfig
	): ?string {
		// Get space keys for both spaces
		$currentSpaceKey = $this->getSpaceKeyFromSpaceId( $currentSpaceId );
		$targetSpaceKey = $this->getSpaceKeyFromSpaceId( $targetSpaceId );

		// If target space key is unknown, return null
		if ( $targetSpaceKey === null ) {
			return null;
		}

		// If current space key is unknown, use interwiki_title
		if ( $currentSpaceKey === null ) {
			return $this->getInterwikiPageTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
		}

		// Get wiki names for both spaces
		$currentWikiName = $wikiConfig->getWikiNameForSpaceKey( $currentSpaceKey );
		$targetWikiName = $wikiConfig->getWikiNameForSpaceKey( $targetSpaceKey );

		// If both spaces belong to the same wiki, use wiki_title
		if ( $currentWikiName === $targetWikiName ) {
			return $this->getWikiPageTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
		}

		// Different wiki: use interwiki_title
		return $this->getInterwikiPageTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
	}

	/**
	 * Get the appropriate blog post title for a link based on wiki comparison.
	 * If the current space and target space belong to the same wiki (per WikiConfig),
	 * returns the wiki_title. Otherwise returns the interwiki_title.
	 *
	 * @param int $currentSpaceId
	 * @param int $targetSpaceId
	 * @param string $confluenceTitle
	 * @param WikiConfig $wikiConfig
	 * @return string|null
	 */
	public function getBlogPostTitleForLink(
		int $currentSpaceId, int $targetSpaceId, string $confluenceTitle, WikiConfig $wikiConfig
	): ?string {
		// Get space keys for both spaces
		$currentSpaceKey = $this->getSpaceKeyFromSpaceId( $currentSpaceId );
		$targetSpaceKey = $this->getSpaceKeyFromSpaceId( $targetSpaceId );

		// If target space key is unknown, return null
		if ( $targetSpaceKey === null ) {
			return null;
		}

		// If current space key is unknown, use interwiki_title
		if ( $currentSpaceKey === null ) {
			return $this->getInterwikiBlogPostTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
		}

		// Get wiki names for both spaces
		$currentWikiName = $wikiConfig->getWikiNameForSpaceKey( $currentSpaceKey );
		$targetWikiName = $wikiConfig->getWikiNameForSpaceKey( $targetSpaceKey );

		// If both spaces belong to the same wiki, use wiki_title
		if ( $currentWikiName === $targetWikiName ) {
			return $this->getWikiBlogPostTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
		}

		// Different wiki: use interwiki_title
		return $this->getInterwikiBlogPostTitleFromSpaceId( $targetSpaceId, $confluenceTitle );
	}
}
