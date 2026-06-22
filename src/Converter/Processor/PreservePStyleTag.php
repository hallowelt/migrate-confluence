<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

class PreservePStyleTag extends ConversionHelper implements IProcessor {

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
				$attributes[$name] = "$name=\"$value\"";
			}

			if ( count( $attributes ) > 1 || !isset( $attributes['style'] ) ) {
				continue;
			}

			$attributesString = implode( ' ', $attributes );

			$openingTagReplacement = $this->createTextNode(
				$tag->ownerDocument,
				"#####PRESERVEPSTYLEOPEN $attributesString#####",
				__METHOD__
			);

			$closingTagReplacement = $this->createTextNode(
				$tag->ownerDocument,
				"#####PRESERVEPSTYLECLOSE#####",
				__METHOD__
			);

			$tag->prepend( $openingTagReplacement );
			$tag->append( $closingTagReplacement );
		}
	}
}
