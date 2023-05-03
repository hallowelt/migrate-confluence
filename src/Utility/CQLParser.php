<?php

namespace HalloWelt\MigrateConfluence\Utility;

class CQLParser {

	/**
	 * @param string $cql
	 * @return string
	 */
	public function parse( string $cql ): string {
		$parsedCQL = '';

		$parsedCQL = preg_replace_callback(
			'#\s*(.*?)\s*=\s*\"(.*?)\"\s*(and|or)*#',
			'self::cqlReplacement',
			$cql
		);

		if ( !$parsedCQL ) {
			return $cql;
		}

		return $parsedCQL;
	}

	/**
	 * @param array $matches
	 * @return string
	 */
	private static function cqlReplacement( array $matches ): string {
		$type = $matches[1];

		if ( $type !== 'label' ) {
			// Currently we do only support 'label'
			return $matches[0];
		}
		$value = $matches[2];

		$operator = '';
		if ( isset( $matches[3] ) ) {
			// Currently we do only support "and" and "or"
			if ( $matches[3] === 'and' ) {
				$operator = '';
			} elseif ( $matches[3] === 'or' ) {
				$operator = '|';
			}
		}

		return '[[Category:' . ucfirst( $value ) . ']]' . $operator;
	}
}
