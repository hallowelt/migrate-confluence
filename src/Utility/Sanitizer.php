<?php

namespace HalloWelt\MigrateConfluence\Utility;

class Sanitizer {

	/**
	 * @param string $namespace_name
	 * @return string
	 */
	public static function sanitizeNamespace( string $namespace_name ): string {
		return preg_replace( '#_+#', '_',
			str_replace(
				[ ' ', ':', ';', ',', '#', '+', '?', '*', '~', '"', "'" ],
				'_',
				trim( $namespace_name )
			) );
	}

	/**
	 * @param string $wiki_name
	 * @return string
	 */
	public static function sanitizeWikiName( string $wiki_name ): string {
		return self::sanitizeNamespace( $wiki_name );
	}

	/**
	 * Makes a Confluence label usable as a MediaWiki category name.
	 * Title-illegal characters become spaces.
	 *
	 * @param string $label
	 * @return string Empty if nothing is left after sanitizing
	 */
	public static function sanitizeCategory( string $label ): string {
		$name = trim( preg_replace( '/[#<>\[\]|{}\s]+/u', ' ', $label ) );
		return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
	}
}
