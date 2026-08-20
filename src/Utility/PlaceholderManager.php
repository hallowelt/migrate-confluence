<?php

namespace HalloWelt\MigrateConfluence\Utility;

class PlaceholderManager {
	private array $placeholders = [];

	/**
	 * the placeholder template is chosen to look like an attribute, so that
	 * pandoc keeps it even in situations where we are inside an element tag.
	 *
	 * The pseudo-namespace syntax is tested up untill pandoc 3.11 to produce
	 * the expected result.
	 */
	private const PLACEHOLDER_TEMPLATE = 'convert:placeholder="%d"';

	public function getPlaceholder( $string ): string {
		if ( !isset( $this->placeholders[ $string ] ) ) {
			$key = sprintf( self::PLACEHOLDER_TEMPLATE, count( $this->placeholders ) );
			$this->placeholders[ $key ] = $string;
		}
		return $key;
	}

	public function replacePlaceholders( string $string ): string {
		return strtr( $string, $this->placeholders );
	}
}
