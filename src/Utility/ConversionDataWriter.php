<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;

class ConversionDataWriter {

	/**
	 *
	 * @var array
	 */
	private $confluenceFiles = [];

	/**
	 * @param DataBuckets $buckets
	 * @return ConversionDataWriter
	 */
	public static function newFromBuckets( DataBuckets $buckets ) {
		return new static(
			$buckets->getBucketData( 'global-files' )
		);
	}

	/**
	 * @param array $confluenceFiles
	 */
	public function __construct( array $confluenceFiles ) {
		$this->confluenceFiles = $confluenceFiles;
	}

	/**
	 * @param string $targetFileName
	 * @param string $newFileContent
	 * @return void
	 */
	public function replaceConfluenceFileContent( string $targetFileName, string $newFileContent ): void {
		if ( isset( $this->confluenceFiles[$targetFileName] ) ) {
			file_put_contents( $this->confluenceFiles[$targetFileName][0], $newFileContent );
		}
	}
}
