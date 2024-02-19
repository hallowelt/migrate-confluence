<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 *
 */
class PreserveTimeTag implements IProcessor {
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
			$replacement = $dom->createElement( 'span' );
			$replacement->setAttribute( 'class', 'PRESERVEDATETIME' );
			$replacement->nodeValue = $element->getAttribute( 'datetime' );

			$element->parentNode->replaceChild( $replacement, $element );
		}
	}

}
