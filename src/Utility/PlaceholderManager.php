<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * manage placeholders to bypass pandoc
 */
class PlaceholderManager {
	private array $placeholders = [];

	/**
	 * the placeholder template is chosen to look like a namespaced attribute, so that
	 * pandoc keeps it even in situations where we are inside an element tag.
	 *
	 * The pseudo-namespace syntax is tested up until pandoc 3.11 to produce
	 * the expected result. To work around quoting issues (i.e., pandoc might
	 * add quotes around the attribute value, so that the placeholder does not
	 * match anymore) you need to decide on use, whether you need the
	 * placeholder to actually look like an attribute.
	 * @var array
	 */
	private const PLACEHOLDER_TEMPLATES = [
		'body' => 'convert:placeholder=%d',
		'attribute' => 'convert:placeholder="%d"',
	];

	/**
	 * get a placeholder
	 *
	 * @param string $string the input string to be replaced
	 * @param string $context the HTML context in which the placeholder will be used (text node or attribute)
	 * @return string a pandoc-safe placeholder to be used instead
	 */
	public function getPlaceholder( string $string, string $context = 'body' ): string {
		if ( !isset( self::PLACEHOLDER_TEMPLATES[ $context ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid placeholder context: %s', $context ) );
		}
		if ( !isset( $this->placeholders[ $string ] ) ) {
			$key = sprintf( self::PLACEHOLDER_TEMPLATES[ $context ], count( $this->placeholders ) );
			$this->placeholders[ $key ] = $string;
		}
		return $key;
	}

	/**
	 * replace all placeholders to their original values
	 *
	 * strtr() does a bit of magic under the hood by replacing the longest
	 * matches first. This way, "placeholder-12" is always replaced before
	 * "placeholder-1".
	 *
	 * @param string $string the content that contains placeholder markers
	 * @return string the content with placeholders replaced by original values
	 */
	public function replacePlaceholders( string $string ): string {
		return strtr( $string, $this->placeholders );
	}
}
