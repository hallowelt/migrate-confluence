<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ConversionDataWriter {

	/** @var string */
	private string $dest;

	/**
	 * @param string $dest
	 */
	public function __construct( string $dest ) {
		$this->dest = $dest;
	}

	/**
	 * @param string $targetFileName
	 * @param string $newFileContent
	 * @return void
	 */
	public function replaceConfluenceFileContent( string $targetFileName, string $newFileContent ): void {
		if ( !is_dir( $this->dest . '/images' ) ) {
			mkdir( $this->dest . '/images', 0755, true );
		}
		file_put_contents( $this->dest . "/images/$targetFileName", $newFileContent );
	}
}
