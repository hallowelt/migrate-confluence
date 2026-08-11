<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * Appends `[[Category:...]]` tags to the wikitext of content that was marked invalid
 * (oversized body content, or an invalid wiki title detected during extraction).
 */
class InvalidContentCategories implements IPostprocessor {

	public const REASON_BODY_TOO_LONG = 'BodyContent exceeded length of 512 characters';

	/** Maps an invalid-content reason text to the category it results in on the page. */
	private const REASON_CATEGORY_MAP = [
		self::REASON_BODY_TOO_LONG                      => 'Overly_long_page',
		'Title ends with invalid character'             => 'Invalid_title_ends_with_invalid_character',
		'Invalid namespace character detected'          => 'Invalid_title_namespace_character',
		'Title contains multiple colons'                => 'Invalid_title_multiple_colons',
		'Title contains too many characters (>255)'     => 'Invalid_title_too_long',
	];

	/**
	 * @param string $invalidReasonText concatenation of the applicable invalid reason texts
	 */
	public function __construct( private readonly string $invalidReasonText ) {
	}

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		if ( $this->invalidReasonText === '' ) {
			return $wikiText;
		}

		$categories = '';
		foreach ( self::REASON_CATEGORY_MAP as $reason => $category ) {
			if ( str_contains( $this->invalidReasonText, $reason ) ) {
				$categories .= "\n[[Category:$category]]";
			}
		}
		if ( $categories === '' ) {
			return $wikiText;
		}

		return rtrim( $wikiText ) . $categories;
	}
}
