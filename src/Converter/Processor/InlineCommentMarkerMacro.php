<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 *
 */
class InlineCommentMarkerMacro implements IProcessor {
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
				$macroNode->ownerDocument->createTextNode(
					"{{InlineComment|{$macroNode->nodeValue}}}"
				),
				$macroNode
			);
		}
	}
}
