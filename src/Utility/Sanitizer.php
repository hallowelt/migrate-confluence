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
}
