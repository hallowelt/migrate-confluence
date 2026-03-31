<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;

class ConversionDataLookup {

	/**
	 * @var array
	 */
	private array $spaceIdPrefixMap;

	/**
	 * @var array
	 */
	private array $spaceIdToKeyMap;

	/**
	 * @var array
	 */
	private array $spaceKeyToIdMap;

	/**
	 * @var array
	 */
	private array $pagesTitlesMap = [];

	/**
	 * @var array
	 */
	private array $filenamesToFiletitlesMap;

	/**
	 * @var array
	 */
	private array $attachmentOrigFilenameToTargetFilenameMap = [];

	/**
	 * @var array
	 */
	private array $files;

	/**
	 * @var array
	 */
	private array $userMap;

	/**
	 * @var array
	 */
	private array $attachmentMetadataMap;

	/**
	 * @param DataBuckets $buckets
	 * @return ConversionDataLookup
	 */
	public static function newFromBuckets( DataBuckets $buckets ): ConversionDataLookup {
		return new static(
			$buckets->getBucketData( 'global-space-id-to-prefix-map' ),
			$buckets->getBucketData( 'global-pages-titles-map' ),
			$buckets->getBucketData( 'global-filenames-to-filetitles-map' ),
			$buckets->getBucketData( 'global-attachment-orig-filename-target-filename-map' ),
			$buckets->getBucketData( 'global-files' ),
			$buckets->getBucketData( 'global-userkey-to-username-map' ),
			$buckets->getBucketData( 'global-space-id-to-key-map' ),
			$buckets->getBucketData( 'global-attachment-metadata' ),
			$buckets->getBucketData( 'global-attachment-id-to-confluence-file-key-map' )
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
	 * @param array $attachmentMetadata
	 * @param array $attachmentIdToFileKeyMap
	 */
	public function __construct(
		array $spaceIdPrefixMap, array $pagesTitlesMap,
		array $filenamesToFiletitlesMap, array $attachmentOrigFilenameToTargetFilenameMap,
		array $files, array $userMap, array $spaceIdToKeyMap,
		array $attachmentMetadata, array $attachmentIdToFileKeyMap
	) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->spaceIdToKeyMap = $spaceIdToKeyMap;
		$this->spaceKeyToIdMap = array_flip( $this->spaceIdToKeyMap );

		// This is some quickfix solution. It must be changed as soon as possible!
		// The real issue is in the way the `analyze` step constructs the "conflucence-keys"
		// within the "maps". It does no normalization there. This whole mechanism should be
		// reviewed.
		foreach ( $pagesTitlesMap as $confluencePageKey => $targetTitle ) {
			$normalConfluencePageKey = str_replace( ' ', '_', $confluencePageKey );
			$this->pagesTitlesMap[$normalConfluencePageKey] = $targetTitle;
		}
		$this->filenamesToFiletitlesMap = $filenamesToFiletitlesMap;
		foreach ( $attachmentOrigFilenameToTargetFilenameMap as $origFilename => $filenames ) {
			$filename = str_replace( ' ', '_', $origFilename );
			$this->attachmentOrigFilenameToTargetFilenameMap[$filename] = $filenames;
		}
		$this->files = $files;
		$this->userMap = $userMap;

		$attachmentMetadataMap = [];
		foreach ( $attachmentMetadata as $attachmentId => $meta ) {
			if ( isset( $attachmentIdToFileKeyMap[$attachmentId] ) ) {
				$attachmentMetadataMap[$attachmentIdToFileKeyMap[$attachmentId]] = $meta;
			}
		}
		$this->attachmentMetadataMap = $attachmentMetadataMap;
	}

	/**
	 * @param string $spaceKey
	 *
	 * @return int
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): int {
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
	 *
	 * @return int|string
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): int|string {
		$id = -1;
		if ( isset( $this->spaceKeyToIdMap[$spaceKey] ) ) {
			$id = $this->spaceKeyToIdMap[$spaceKey];
		}

		if ( isset( $this->spaceIdPrefixMap[$id] ) ) {
			$spacePrefix = $this->spaceIdPrefixMap[$id];
			// See `ConfluenceAnalyzer::makeSpacesMap`
			if ( $spacePrefix === 'GENERAL' ) {
				$spacePrefix = '';
			} else {
				$spacePrefix = substr( $spacePrefix, 0, strpos( $spacePrefix, ':' ) );
			}
			return $spacePrefix;
		}
		return -1;
	}

	/**
	 *
	 * @param string $confluencePageKey
	 *
	 * @return string
	 */
	public function getTargetTitleFromConfluencePageKey( string $confluencePageKey ): string {
		$confluencePageKey = str_replace( ' ', '_', $confluencePageKey );
		if ( isset( $this->pagesTitlesMap[$confluencePageKey] ) ) {
			return $this->pagesTitlesMap[$confluencePageKey];
		}
		return '';
	}

	/**
	 *
	 * @param string $confluenceFileKey
	 *
	 * @return string
	 */
	public function getTargetFileTitleFromConfluenceFileKey( string $confluenceFileKey ): string {
		if ( isset( $this->filenamesToFiletitlesMap[$confluenceFileKey] ) ) {
			return $this->filenamesToFiletitlesMap[$confluenceFileKey];
		}

		$confluenceFileKey = str_replace( ' ', '_', $confluenceFileKey );
		if ( isset( $this->filenamesToFiletitlesMap[$confluenceFileKey] ) ) {
			return $this->filenamesToFiletitlesMap[$confluenceFileKey];
		}

		$confluenceFileKeyParts = explode( '---', $confluenceFileKey );
		if ( count( $confluenceFileKeyParts ) < 3 ) {
			return '';
		}
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
	 * Returns all target file titles attached to a given page.
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string[]
	 */
	public function getTargetFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		$prefix = $spaceId . '---' . str_replace( ' ', '_', basename( $rawPageTitle ) ) . '---';
		$results = [];
		foreach ( $this->filenamesToFiletitlesMap as $key => $targetTitle ) {
			if ( strpos( $key, $prefix ) === 0 && $targetTitle !== '' ) {
				$results[] = $targetTitle;
			}
		}
		return $results;
	}

	/**
	 * Returns target file titles with their full metadata for all attachments on a page.
	 * The returned array is keyed by confluence file key. Each value contains 'targetTitle'
	 * plus any additional metadata fields (e.g. 'labels', 'mediaType', etc.).
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return array<string, array> Map of confluenceFileKey => metadata (including 'targetTitle')
	 */
	public function getAttachmentMetadataForPage( int $spaceId, string $rawPageTitle ): array {
		$prefix = $spaceId . '---' . str_replace( ' ', '_', basename( $rawPageTitle ) ) . '---';
		$results = [];
		foreach ( $this->filenamesToFiletitlesMap as $key => $targetTitle ) {
			if ( strpos( $key, $prefix ) !== 0 || $targetTitle === '' ) {
				continue;
			}
			$results[$key] = array_merge(
				$this->attachmentMetadataMap[$key] ?? [],
				[ 'targetTitle' => $targetTitle ]
			);
		}
		return $results;
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
