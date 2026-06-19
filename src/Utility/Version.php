<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * provide the tool's version
 */
class Version {

	/**
	 * @var string|null deduced version
	 */
	private static string|null $version = null;

	public static function getVersion(): string {
		if ( static::$version === null ) {
			$rawVersion = file_get_contents( __DIR__ . '/../../VERSION' );
			if ( is_string( $rawVersion ) ) {
				$rawVersion = trim( $rawVersion );
			}
			if ( $rawVersion ) {
				static::$version = $rawVersion;
			}
		}
		return static::$version ?? 'unknown';
	}

}
