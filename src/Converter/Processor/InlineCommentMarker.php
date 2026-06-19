<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

/**
 *
 */
class InlineCommentMarker extends ConversionHelper implements IProcessor {
	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$processorNodes = $dom->getElementsByTagName( 'inline-comment-marker' );

		$macroNodes = [];
		foreach ( $processorNodes as $processorNode ) {
			$macroNodes[] = $processorNode;
		}

		foreach ( $macroNodes as $macroNode ) {
			$macroNode->parentNode->replaceChild(
				$this->createTextNode(
					$macroNode->ownerDocument,
					"{{InlineComment|$macroNode->nodeValue}}",
					__METHOD__
				),
				$macroNode
			);
		}
	}
}
