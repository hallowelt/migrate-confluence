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
		if ( !is_dir( $this->dest . '/images' ) ) {
			if ( !mkdir( $this->dest . '/images', 0755, true ) && !is_dir( $this->dest . '/images' ) ) {
				throw new RuntimeException( "Failed to create directory: {$this->dest}/images" );
			}
		}
		if ( file_put_contents( $this->dest . "/images/$targetFileName", $newFileContent ) === false ) {
			throw new RuntimeException( "Failed to write file: {$this->dest}/images/$targetFileName" );
		}
	}
}
