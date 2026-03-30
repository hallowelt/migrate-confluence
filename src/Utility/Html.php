<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DomainException;
use DOMDocument;
use DOMException;
use DOMNode;

class Html {
	/**
	 * Creates and returns as DOMNode object specified HTML element with specified attributes.
	 *
	 * @param DOMDocument $dom DOMDocument which is used as context.
	 * @param string $tag Element tag.
	 * @param array $attributes Element attributes.
	 *
	 * @return DOMNode|null Necessary HTML element. Null if for some reason it was not created.
	 * @throws DOMException
	 */
	public static function element( DOMDocument $dom, string $tag, array $attributes ): ?DOMNode {
		switch ( $tag ) {
			case 'img':
				$element = $dom->createElement( 'img' );
				foreach ( $attributes as $attribute => $value ) {
					$element->setAttribute( $attribute, $value );
				}
				break;
			default:
				throw new DomainException( 'Unsupported element tag - ' . $tag );
		}

		return $element;
	}

}
