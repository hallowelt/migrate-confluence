<?php

namespace HalloWelt\MigrateConfluence\Utility;

use RuntimeException;

class ConversionDataWriter {

	/**
	 * @param string $dest
	 */
	public function __construct( private readonly string $dest ) {
	}

	/**
	 * @param string $targetFileName
	 * @param string $newFileContent
	 * @return void
	 */
	public function replaceConfluenceFileContent( string $targetFileName, string $newFileContent ): void {
		DirectoryHelper::ensure( $this->dest . '/images' );
		if ( file_put_contents( $this->dest . "/images/$targetFileName", $newFileContent ) === false ) {
			throw new RuntimeException( "Failed to write file: {$this->dest}/images/$targetFileName" );
		}
	}
}
