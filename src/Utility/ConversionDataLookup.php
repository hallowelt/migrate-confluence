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
	 * @param DataBuckets $buckets
	 * @return ConversionDataLookup
	 */
	public static function newFromBuckets( DataBuckets $buckets ) {
		return new static(
			$buckets->getBucketData( 'space-id-to-prefix-map' ),
			$buckets->getBucketData( 'pages-titles-map' ),
			$buckets->getBucketData( 'filenames-to-filetitles-map' )
		);
	}

	/**
	 *
	 * @param array $spaceIdPrefixMap
	 * @param array $confluencePageKeyTargetTitleMap
	 * @param array $confluenceFilenameTargetFiletitleMap
	 */
	public function __construct( $spaceIdPrefixMap, $confluencePageKeyTargetTitleMap,
		$confluenceFilenameTargetFiletitleMap ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->confluencePageKeyTargetTitleMap = $confluencePageKeyTargetTitleMap;
		$this->confluenceFilenameTargetFiletitleMap = $confluenceFilenameTargetFiletitleMap;
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
		if ( isset( $this->confluenceFilenameTargetFiletitleMap[$confluenceFileKey] ) ) {
			return $this->confluenceFilenameTargetFiletitleMap[$confluenceFileKey];
		}
		return '';
	}
}
