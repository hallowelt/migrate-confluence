<?php

namespace HalloWelt\MigrateConfluence\Utility;

class CQLParser {

	/**
	 * @param string $cql
	 * @return string
	 */
	public function parse( string $cql ): string {
		$parsedCQL = '';

		$parsedCQL = preg_replace_callback( '#label\s*=\s*\"(.*?)\"\s*#', static function ( $matches ) {
			$label = $matches[1];
			$label = ucfirst( $label );
			return "[[Category:$label]]";
		}, $cql );
		$parsedCQL = preg_replace_callback( '#\]\]\s*(and|AND)\s*#', static function () {
			return "]]";
		}, $parsedCQL );
		$parsedCQL = preg_replace_callback( '#\]\]\s*(or|OR)\s*#', static function () {
			return "]]|";
		}, $parsedCQL );

		if ( !$parsedCQL ) {
			return $cql;
		}

		return $parsedCQL;
	}
}
