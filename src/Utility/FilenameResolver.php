<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;

/**
 * Resolves Confluence file data to a MediaWiki file title.
 * When no mapping is found, falls back to generated file title so callers can
 * always render a (red) link. Use 'isBroken' to append a maintenance category.
 */
class FilenameResolver {

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		protected DBConversionDataLookup $dataLookup,
		protected MigrationConfig $migrationConfig ) {
	}

	/**
	 * @param int $spaceId
	 * @param string $confluencePageTitle
	 * @param string $filename
	 *
	 * @return array
	 * @throws Exception
	 */
	public function resolve( int $spaceId, string $confluencePageTitle, string $filename ): array {
		$fileTitle = $this->dataLookup->getWikiFileTitleFromSpaceId(
			$spaceId,
			$confluencePageTitle,
			$filename
		) ?? '';
		if ( $fileTitle !== '' ) {
			return $this->getResult( $fileTitle, false );
		}

		$fileTitle = $this->buildFileTitle( $spaceId, $confluencePageTitle, $filename );

		return $this->getResult( $fileTitle, true );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluencePageTitle
	 * @param string $filename
	 *
	 * @return string
	 * @throws Exception
	 */
	private function buildFileTitle( int $spaceId, string $confluencePageTitle, string $filename ): string {
		$filenameBuilder = new FilenameBuilder(
			$this->dataLookup->getSpaceIdToPrefixMap(),
			$this->migrationConfig
		);

		$shortPageWikiTitle = $this->createShortPageWikiTitle( $spaceId, $confluencePageTitle );

		try {
			$fileTitle = $filenameBuilder->buildFromAttachmentData( $spaceId, $filename, $shortPageWikiTitle );
		} catch ( InvalidTitleException $ex ) {
			$fileTitle = $ex->getInvalidTitle();
		}

		return $fileTitle;
	}

	/**
	 * @param int $spaceId
	 * @param string $confluencePageTitle
	 *
	 * @return string
	 * @throws Exception
	 */
	private function createShortPageWikiTitle( int $spaceId, string $confluencePageTitle ): string {
		$assocTitle = $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $confluencePageTitle );
		if ( !$assocTitle ) {
			$assocTitle = $this->dataLookup->getWikiBlogPostTitleFromSpaceId( $spaceId, $confluencePageTitle );
		}

		if ( !$assocTitle ) {
			return "";
		}

		$pageWikiTitleParts = substr( $assocTitle, strrpos( $assocTitle, ':' ) + 1 );
		$pageWikiTitleParts = explode( '/', $pageWikiTitleParts );

		return end( $pageWikiTitleParts );
	}

	/**
	 * @param string $title
	 * @param bool $broken
	 * @return array
	 */
	private function getResult( string $title, bool $broken ): array {
		return [ 'title' => $title, 'isBroken' => $broken ];
	}
}
