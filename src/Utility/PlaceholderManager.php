<?php

namespace HalloWelt\MigrateConfluence\Utility;

class PlaceholderManager {
	private array $placeholders = [];

	private const PLACEHOLDER_TEMPLATE = '___PLACEHOLDER_%d___';

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
