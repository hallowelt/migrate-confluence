<?php

namespace HalloWelt\MigrateConfluence\Utility;

class CQLParser {

	/**
	 * @param string $cql
	 * @return string
	 */
	public function parse( string $cql ): string {
		$parsedCQL = '';

		$parsedCQL = preg_replace_callback( '#label\s*=\s*\"(.*?)\"\s*#', function( $matches ) {
			$label = $matches[1];
			$label = ucfirst( $label );
			return "[[Category:$label]]";
		}, $cql );
		$parsedCQL = preg_replace_callback( '#\]\]\s*(and|AND)\s*#', function( $matches ) {
			return "]]";
		}, $parsedCQL );
		$parsedCQL = preg_replace_callback( '#\]\]\s*(or|OR)\s*#', function( $matches ) {
			return "]]|";
		}, $parsedCQL );

		if ( !$parsedCQL ) {
			return $cql;
		}

		return $parsedCQL;
	}
}
