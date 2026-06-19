<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
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

		$macroNodes = [];
		foreach ( $processorNodes as $processorNode ) {
			$macroNodes[] = $processorNode;
		}

		foreach ( $macroNodes as $macroNode ) {
			$macroNode->parentNode->replaceChild(
				$this->createTextNode(
					$macroNode->ownerDocument,
					"<!-- $macroNode->textContent -->",
					__METHOD__
				),
				$macroNode
			);
		}
	}
}
