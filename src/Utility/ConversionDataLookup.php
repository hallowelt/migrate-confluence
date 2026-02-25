<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;

class ConversionDataLookup {

	/**
	 *
	 * @var array
	 */
	private $spaceIdPrefixMap = [];

	/**
	 *
	 * @var array
	 */
	private $spaceIdToKeyMap = [];

	/**
	 *
	 * @var array
	 */
	private $pagesTitlesMap = [];

	/**
	 *
	 * @var array
	 */
	private $filenamesToFiletitlesMap = [];

	/**
	 *
	 * @var array
	 */
	private $attachmentOrigFilenameToTargetFilenameMap = [];

	/**
	 *
	 * @var array
	 */
	private $files = [];

	/**
	 * @var array
	 */
	private $userMap = [];

	/**
	 *
	 * @param DataBuckets $buckets
	 * @return ConversionDataLookup
	 */
	public static function newFromBuckets( DataBuckets $buckets ) {
		return new static(
			$buckets->getBucketData( 'global-space-id-to-prefix-map' ),
			$buckets->getBucketData( 'global-pages-titles-map' ),
			$buckets->getBucketData( 'global-filenames-to-filetitles-map' ),
			$buckets->getBucketData( 'global-attachment-orig-filename-target-filename-map' ),
			$buckets->getBucketData( 'global-files' ),
			$buckets->getBucketData( 'global-userkey-to-username-map' ),
			$buckets->getBucketData( 'global-space-id-to-key-map' ),
		);
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $pagesTitlesMap
	 * @param array $filenamesToFiletitlesMap
	 * @param array $attachmentOrigFilenameToTargetFilenameMap
	 * @param array $files
	 * @param array $userMap
	 * @param array $spaceIdToKeyMap
	 */
	public function __construct(
		$spaceIdPrefixMap, $pagesTitlesMap,
		$filenamesToFiletitlesMap, $attachmentOrigFilenameToTargetFilenameMap,
		$files, $userMap, $spaceIdToKeyMap ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdToKeyMap = $spaceIdToKeyMap;

		// This is some quickfix solution. It must be changed as soon as possible!
		// The real issue is in the way the `analyze` step constructs the "conflucence-keys"
		// within the "maps". It does no normalization there. This whole mechanism should be
		// reviewed.
		foreach ( $pagesTitlesMap as $confluencePageKey => $targetTitle ) {
			$normalConfluencePageKey = str_replace( ' ', '_', $confluencePageKey );
			$this->pagesTitlesMap[$normalConfluencePageKey] = $targetTitle;
		}
		foreach ( $filenamesToFiletitlesMap as $confluenceFileKey => $targetTitle ) {
			$this->filenamesToFiletitlesMap = $filenamesToFiletitlesMap;
		}
		foreach ( $attachmentOrigFilenameToTargetFilenameMap as $origFilename => $filenames ) {
			$filename = str_replace( ' ', '_', $origFilename );
			$this->attachmentOrigFilenameToTargetFilenameMap[$filename] = $filenames;
		}
		$this->files = $files;
		$this->userMap = $userMap;
	}

	/**
	 * @param string $spaceKey
	 * @return int
	 */
	public function getSpaceIdFromSpaceKey( $spaceKey ) {
		// See `ConfluenceAnalyzer::makeSpacesMap`
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}
		foreach ( $this->spaceIdToKeyMap as $spaceId => $key ) {
			if ( $key === $spaceKey ) {
				return $spaceId;
			}
		}
		return -1;
	}

	/**
	 * Get the mediawiki namespace for a given space key.
	 * This is required if the namespace prefix for the namespace
	 * is overwritten by custom config file.
	 *
	 * @param string $spaceKey
	 * @return int
	 */
	public function getSpacePrefixFromSpaceKey( $spaceKey ) {
		$spacePrefix = '';
		if ( isset( $this->spaceKeyPrefixMap[$spaceKey] ) ) {
			$spacePrefix = $this->spaceKeyPrefixMap[$spaceKey];
			// See `ConfluenceAnalyzer::makeSpacesMap`
			if ( $spacePrefix === 'GENERAL' ) {
				$spacePrefix = '';
			}
			return $spacePrefix;
		}
		return -1;
	}

	/**
	 *
	 * @param string $confluencePageKey
	 * @return string
	 */
	public function getTargetTitleFromConfluencePageKey( $confluencePageKey ) {
		$confluencePageKey = str_replace( ' ', '_', $confluencePageKey );
		if ( isset( $this->pagesTitlesMap[$confluencePageKey] ) ) {
			return $this->pagesTitlesMap[$confluencePageKey];
		}
		return '';
	}

	/**
	 *
	 * @param string $confluenceFileKey
	 * @return string
	 */
	public function getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey ) {
		if ( isset( $this->filenamesToFiletitlesMap[$confluenceFileKey] ) ) {
			return $this->filenamesToFiletitlesMap[$confluenceFileKey];
		}

		$confluenceFileKey = str_replace( ' ', '_', $confluenceFileKey );
		if ( isset( $this->filenamesToFiletitlesMap[$confluenceFileKey] ) ) {
			return $this->filenamesToFiletitlesMap[$confluenceFileKey];
		}

		$confluenceFileKeyParts = explode( '---', $confluenceFileKey );
		$confluenceFilename = $confluenceFileKeyParts[2];

		$md5File = '';
		$filename = '';
		if ( isset( $this->attachmentOrigFilenameToTargetFilenameMap[$confluenceFilename] ) ) {
			$filenames = $this->attachmentOrigFilenameToTargetFilenameMap[$confluenceFilename];
			foreach ( $filenames as $curFilename ) {
				if ( !isset( $this->files[$curFilename] ) ) {
					continue;
				}
				$paths = $this->files[$curFilename];
				$curFile = file_get_contents( $paths[0] );
				$curFileMd5 = md5( $curFile );
				if ( $md5File === '' ) {
					$md5File = $curFileMd5;
				}
				if ( $md5File !== $curFileMd5 ) {
					// It might be that not all files with equal filename are equal files
					return '';
				}
				$filename = $curFilename;
			}
		}
		return $filename;
	}

	/**
	 * @param string $userKey
	 * @return string
	 */
	public function getUsernameFromUserKey( string $userKey ): string {
		if ( isset( $this->userMap[ $userKey ] ) ) {
			return $this->userMap[ $userKey ];
		}

		return $userKey;
	}

	/**
	 * @param string $fileName
	 * @return string|null
	 */
	public function getConfluenceFileContent( string $fileName ): ?string {
		if ( isset( $this->files[$fileName] ) ) {
			return file_get_contents( $this->files[$fileName][0] );
		}

		return null;
	}
}
