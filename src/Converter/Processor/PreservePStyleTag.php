<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class PreservePStyleTag implements IProcessor {

	/**
	 * Pandoc removes p tags with style
	 *
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$tags = $dom->getElementsByTagName( 'p' );

		foreach ( $tags as $tag ) {
			if ( $tag instanceof DOMElement === false ) {
				continue;
			}

			if ( !$tag->hasAttributes() ) {
				continue;
			}

			$attributes = [];
			$attributeMap = $tag->attributes;
			for ( $index = 0; $index < count( $attributeMap ); $index++ ) {
				$name = $attributeMap->item( $index )->nodeName;
				$value = $attributeMap->item( $index )->nodeValue;
				$attributes[$name] = "{$name}=\"{$value}\"";
			}

			if ( count( $attributes ) > 1 || !isset( $attributes['style'] ) ) {
				continue;
			}

			$attributesString = implode( ' ', $attributes );

			$openingTagReplacement = $tag->ownerDocument->createTextNode(
				"#####PRESERVEPSTYLEOPEN $attributesString#####"
			);

			$closeingTagReplacement = $tag->ownerDocument->createTextNode(
				"#####PRESERVEPSTYLECLOSE#####"
			);

			$tag->prepend( $openingTagReplacement );
			$tag->append( $closeingTagReplacement );
		}
	}
}
