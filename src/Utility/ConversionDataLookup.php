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
	private $confluencePageKeyTargetTitleMap = [];

	/**
	 *
	 * @var array
	 */
	private $confluenceFilenameTargetFiletitleMap = [];

	/**
	 *
	 * @var array
	 */
	private $confluenceAttachmentOrigFilenameToTargetFilenameMap = [];

	/**
	 *
	 * @var array
	 */
	private $confluenceFiles = [];

	/**
	 *
	 * @param DataBuckets $buckets
	 * @return ConversionDataLookup
	 */
	public static function newFromBuckets( DataBuckets $buckets ) {
		return new static(
			$buckets->getBucketData( 'space-id-to-prefix-map' ),
			$buckets->getBucketData( 'pages-titles-map' ),
			$buckets->getBucketData( 'filenames-to-filetitles-map' ),
			$buckets->getBucketData( 'attachment-orig-filename-target-filename-map' ),
			$buckets->getBucketData( 'files' )
		);
	}

	/**
	 *
	 * @param array $spaceIdPrefixMap
	 * @param array $confluencePageKeyTargetTitleMap
	 * @param array $confluenceFilenameTargetFiletitleMap
	 * @param array $confluenceAttachmentOrigFilenameToTargetFilenameMap
	 * @param array $confluenceFiles
	 */
	public function __construct( $spaceIdPrefixMap, $confluencePageKeyTargetTitleMap,
		$confluenceFilenameTargetFiletitleMap, $confluenceAttachmentOrigFilenameToTargetFilenameMap,
		$confluenceFiles ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;

		// This is some quickfix solution. It must be changed as soon as possible!
		// The real issue is in the way the `analyze` step constructs the "conflucence-keys"
		// within the "maps". It does no normalization there. This whole mechanism should be
		// reviewed.
		foreach ( $confluencePageKeyTargetTitleMap as $confluencePageKey => $targetTitle ) {
			$normalConfluencePageKey = str_replace( ' ', '_', $confluencePageKey );
			$this->confluencePageKeyTargetTitleMap[$normalConfluencePageKey] = $targetTitle;
		}
		foreach ( $confluenceFilenameTargetFiletitleMap as $confluenceFileKey => $targetTitle ) {
			$normalConfluenceFileKey = str_replace( ' ', '_', $confluenceFileKey );
			$this->confluenceFilenameTargetFiletitleMap[$normalConfluenceFileKey] = $targetTitle;
		}
		foreach ( $confluenceAttachmentOrigFilenameToTargetFilenameMap as $origFilename => $filenames ) {
			$filename = str_replace( ' ', '_', $origFilename );
			$this->confluenceAttachmentOrigFilenameToTargetFilenameMap[$filename] = $filenames;
		}
		$this->confluenceFiles = $confluenceFiles;
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
		foreach ( $this->spaceIdPrefixMap as $spaceId => $prefix ) {
			if ( $prefix === $spaceKey ) {
				return $spaceId;
			}
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
		if ( isset( $this->confluencePageKeyTargetTitleMap[$confluencePageKey] ) ) {
			return $this->confluencePageKeyTargetTitleMap[$confluencePageKey];
		}
		return '';
	}

	/**
	 *
	 * @param string $confluenceFileKey
	 * @return string
	 */
	public function getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey ) {
		$confluenceFileKey = str_replace( ' ', '_', $confluenceFileKey );
		if ( isset( $this->confluenceFilenameTargetFiletitleMap[$confluenceFileKey] ) ) {
			return $this->confluenceFilenameTargetFiletitleMap[$confluenceFileKey];
		}
		$confluenceFileKeyParts = explode( '---', $confluenceFileKey );
		$confluenceFilename = $confluenceFileKeyParts[2];
		$md5File = '';
		$filename = '';
		if ( isset( $this->confluenceAttachmentOrigFilenameToTargetFilenameMap[$confluenceFilename] ) ) {
			$filenames = $this->confluenceAttachmentOrigFilenameToTargetFilenameMap[$confluenceFilename];
			foreach ( $filenames as $curFilename ) {
				if ( !isset( $this->confluenceFiles[$curFilename] ) ) {
					continue;
				}
				$paths = $this->confluenceFiles[$curFilename];
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
}
