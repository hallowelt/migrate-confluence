<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class PreservePStyleTag extends ConversionHelper implements IProcessor, IUsesPlaceholder {

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

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
				$this->placeholderManager->getPlaceholder( "<p $attributesString>" ),
				__METHOD__
			);

			$closingTagReplacement = $this->createTextNode(
				$tag->ownerDocument,
				$this->placeholderManager->getPlaceholder( "</p>" ),
				__METHOD__
			);

			$tag->prepend( $openingTagReplacement );
			$tag->append( $closingTagReplacement );
		}
	}
}
