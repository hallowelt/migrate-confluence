<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

/**
 *
 */
class Placeholder extends ConversionHelper implements IProcessor {
	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$processorNodes = $dom->getElementsByTagName( 'placeholder' );

		$nodes = [];
		foreach ( $processorNodes as $processorNode ) {
			$nodes[] = $processorNode;
		}

		foreach ( $nodes as $processorNode ) {
			$textContent = $processorNode->textContent;

			if ( empty( $textContent ) ) {
				$this->removeNode( $processorNode );
				continue;
			}

			$span = $processorNode->ownerDocument->createElement( 'span' );
			$span->setAttribute( 'class', 'placeholder' );
			$span->appendChild(
				$processorNode->ownerDocument->createTextNode( $textContent )
			);

			$processorNode->parentNode->replaceChild( $span, $processorNode );
		}
	}

	/**
	 * @param DOMNode $processorNode
	 *
	 * @return void
	 */
	private function removeNode( DOMNode $processorNode ): void {
		if ( $processorNode->parentNode === null ) {
			return;
		}
		$prev = $processorNode->previousSibling;
		if (
			$prev !== null &&
			$prev->nodeType === XML_TEXT_NODE &&
			trim( $prev->nodeValue ) === ''
		) {
			$processorNode->parentNode->removeChild( $prev );
		}
		$processorNode->parentNode->removeChild( $processorNode );
	}
}
