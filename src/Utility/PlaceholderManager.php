<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * manage placeholders to bypass pandoc
 */
class PlaceholderManager {
	private array $placeholders = [];

	/**
	 * the placeholder template is chosen to look like an attribute, so that
	 * pandoc keeps it even in situations where we are inside an element tag.
	 *
	 * The pseudo-namespace syntax is tested up until pandoc 3.11 to produce
	 * the expected result.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_TEMPLATE = 'convert:placeholder=%d';

	/**
	 * get a placeholder
	 *
	 * @param string $string the input string to be replaced
	 * @return string a pandoc-safe placeholder to be used instead
	 */
	public function getPlaceholder( string $string ): string {
		if ( !isset( $this->placeholders[ $string ] ) ) {
			$key = sprintf( self::PLACEHOLDER_TEMPLATE, count( $this->placeholders ) );
			$this->placeholders[ $key ] = $string;
		}
		return $key;
	}

	/**
	 * replace all placeholders to their original values
	 *
	 * @param string $string the content that contains placeholder markers
	 * @return string the content with placeholders replaced by original values
	 */
	public function replacePlaceholders( string $string ): string {
		return strtr( $string, $this->placeholders );
	}
}
