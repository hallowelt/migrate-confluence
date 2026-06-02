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
	 * @var DBConversionDataLookup
	 */
	protected DBConversionDataLookup $dataLookup;

	/**
	 * @var MigrationConfig
	 */
	private MigrationConfig $migrationConfig;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct( DBConversionDataLookup $dataLookup, MigrationConfig $migrationConfig ) {
		$this->dataLookup = $dataLookup;
		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @param int $spaceId
	 * @param string $confluencePageTitle
	 * @param string $filename
	 * @return array
	 */
	public function resolve( int $spaceId, string $confluencePageTitle, string $filename ): array {
		$fileTitle = $this->dataLookup->getTargetFileTitleFromSpaceId(
			$spaceId,
			$confluencePageTitle,
			$filename
		);
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
	 */
	private function buildFileTitle( int $spaceId, string $confluencePageTitle, string $filename ): string {
		try {
			$assocTitle = $this->dataLookup->getTargetWikiTitleFromSpaceId( $spaceId, $confluencePageTitle );
		} catch ( Exception $ex ) {
			$assocTitle = '';
		}

		$filenameBuilder = new FilenameBuilder(
			$this->dataLookup->getSpaceIdToPrefixMap(),
			$this->migrationConfig
		);

		$pageWikiTitleParts = substr( $assocTitle, strrpos( $assocTitle, ':' ) );
		$pageWikiTitleParts = explode( '/', $pageWikiTitleParts );
		$shortPageWikiTitle = end( $pageWikiTitleParts );

		try {
			$fileTitle = $filenameBuilder->buildFromAttachmentData( $spaceId, $filename, $shortPageWikiTitle );
		} catch ( InvalidTitleException $ex ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.

				$fileTitle = $filenameBuilder->buildFromAttachmentData( $spaceId, $filename, $shortPageWikiTitle );
			} catch ( InvalidTitleException $ex ) {
				$fileTitle = $ex->getInvalidTitle();
			}
		}
		return $fileTitle;
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
