<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Pandoc drops <time> tags, so we replace them with a placeholder that
 * resolves back to a <datetime> tag once pandoc is done.
 */
class PreserveTimeTag implements IProcessor, IUsesPlaceholder {

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$elements = $dom->getElementsByTagName( 'time' );

		$nonLiveList = [];
		foreach ( $elements as $element ) {
			$nonLiveList[] = $element;
		}

		foreach ( $nonLiveList as $element ) {
			$datetime = $element->getAttribute( 'datetime' );
			/* preserve the tag's content, unless it is just a repeat of the datetime attribute */
			$content = '';
			foreach ( $element->childNodes as $child ) {
				if ( $child->nodeType === XML_ELEMENT_NODE ) {
					$content .= $dom->saveHTML( $child );
				} elseif ( $child->nodeType === XML_TEXT_NODE && $child->nodeValue && $child->nodeValue !== $datetime ) {
					$content .= $dom->saveHTML( $child );
				}
			}
			if ( $content ) {
				$content = " $content";
			}
			$replacement = $dom->createTextNode(
				$this->placeholderManager->getPlaceholder( "<datetime>$datetime</datetime>$content" )
			);

			$element->parentNode->replaceChild( $replacement, $element );
		}
	}

}
