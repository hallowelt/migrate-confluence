<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Table-related macros. Legacy presentational attributes are turned into CSS, since newer Pandoc
 * versions prefix them with "data-", which makes them ineffective.
 */
abstract class LegacyTableMacroBase extends HtmlTagMacroBase {

	private const CSS_PROPERTIES = [
		'valign' => 'vertical-align',
		'bgcolor' => 'background-color',
	];

	/**
	 * @inheritDoc
	 */
	protected function mapParams( array $params ): array {
		$attributes = parent::mapParams( $params );
		foreach ( $attributes as $name => $value ) {
			$css = $this->toCss( $name, $value );
			if ( $css !== null ) {
				unset( $attributes[$name] );
				$attributes = $this->addStyle( $attributes, $css );
			}
		}
		return $attributes;
	}

	/**
	 * Returns null if the attribute has no safe CSS equivalent and must be kept as is.
	 * This includes "cellpadding", which cannot be expressed on the table alone.
	 *
	 * @param string $name
	 * @param string $value
	 * @return string|null
	 */
	private function toCss( string $name, string $value ): ?string {
		$value = trim( $value );
		$length = ctype_digit( $value ) ? $value . 'px' : $value;
		if ( $name === 'nowrap' ) {
			return 'white-space: nowrap;';
		}
		if ( !preg_match( '/^[#\w\s(),.%-]+$/', $value ) ) {
			return null;
		}
		if ( isset( self::CSS_PROPERTIES[$name] ) ) {
			return self::CSS_PROPERTIES[$name] . ': ' . $value . ';';
		}
		switch ( $name ) {
			case 'align':
				return $this->alignToCss( $value );
			case 'border':
				return ctype_digit( $value ) ? "border-width: $length; border-style: solid;" : null;
			case 'cellspacing':
				return ctype_digit( $value ) ? "border-collapse: separate; border-spacing: $length;" : null;
		}
		return null;
	}

	/**
	 * @param string $value
	 * @return string|null
	 */
	private function alignToCss( string $value ): ?string {
		if ( !in_array( $value, [ 'left', 'right', 'center', 'justify' ], true ) ) {
			return null;
		}
		if ( static::TAG !== 'table' ) {
			return "text-align: $value;";
		}
		return match ( $value ) {
			'center' => 'margin-left: auto; margin-right: auto;',
			'left', 'right' => "float: $value;",
			default => null,
		};
	}
}
