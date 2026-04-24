<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;

/**
 * Resolves Confluence file data to a MediaWiki file title.
 * When no mapping is found, falls back to generated file title so callers can
 * always render a (red) link. Use 'isBroken' to append a maintenance category.
 */
class FilenameResolver {

	/**
	 * @var ConversionDataLookup
	 */
	protected ConversionDataLookup $dataLookup;

	/**
	 * @var array
	 */
	private array $config;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param array $config
	 */
	public function __construct( ConversionDataLookup $dataLookup, array $config = [] ) {
		$this->dataLookup = $dataLookup;
		$this->config = $config;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @param string $filename
	 * @return array
	 */
	public function resolve( int $spaceId, string $rawPageTitle, string $filename ): array {
		$confluenceFileKey = $this->generateConfluenceKey( $spaceId, $rawPageTitle, $filename );
		$fileTitle = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );
		if ( $fileTitle !== '' ) {
			return $this->getResult( $fileTitle, false );
		}

		$fileTitle = $this->buildFileTitle( $spaceId, $rawPageTitle, $filename );
		return $this->getResult( $fileTitle, true );
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @param string $filename
	 * @return string
	 */
	private function buildFileTitle( int $spaceId, string $rawPageTitle, string $filename ): string {
		$assocTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( "$spaceId---$rawPageTitle" );

		$filenameBuilder = new FilenameBuilder(
			$this->dataLookup->getSpaceIdToPrefixMap(),
			$this->config
		);

		try {
			$fileTitle = $filenameBuilder->buildFromAttachmentData( $spaceId, $filename, $assocTitle );
		} catch ( InvalidTitleException $ex ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.
				$shortTargetTitle = basename( $assocTitle );
				$fileTitle = $filenameBuilder->buildFromAttachmentData( $spaceId, $filename, $shortTargetTitle );
			} catch ( InvalidTitleException $ex ) {
				$fileTitle = $ex->getInvalidTitle();
			}
		}
		return $fileTitle;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @param string $filename
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle, string $filename ): string {
		return "$spaceId---$rawPageTitle---$filename";
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
