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
		$label = mb_scrub( $label );
		$name = trim( preg_replace_callback( '/[#<>\[\]|{}]|\s+/u', static fn ( $matches ) => match ( $matches[0] ) {
			'#' => ' hash ',
			'<' => ' lower than ',
			'>' => ' greater than ',
			'|' => ' pipe ',
			'[', '{' => '(',
			']', '}' => ')',
			default => ' ',
		}, $label ) );
		$name = ltrim( $name, ':' );
		if ( $name === '.' || $name === '..' ) {
			return '';
		}
		if ( !( new TitleValidityChecker() )->hasValidLength( 'Category:' . $name ) ) {
			// truncate overly long labels. We do not bother with conflict checks, since
			// we consider this a fringe edge case. Substract strlen( 'Category:' ) to stay
			// within the 255 bytes limit.
			$name = mb_strcut( $name, 0, 246 );
		}
		return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
	}
}
