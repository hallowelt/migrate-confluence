<?php

namespace HalloWelt\MigrateConfluence\Utility;

use RuntimeException;

class DirectoryHelper {

	/**
	 * Creates a directory, tolerating parallel workers creating it at the same time.
	 *
	 * @param string $path
	 * @throws RuntimeException If the directory does not exist afterwards
	 */
	public static function ensure( string $path ): void {
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( !is_dir( $path ) && !@mkdir( $path, 0755, true ) && !is_dir( $path ) ) {
			throw new RuntimeException( "Failed to create directory: $path" );
		}
	}
}
