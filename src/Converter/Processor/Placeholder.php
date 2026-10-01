<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 *
 */
class Placeholder extends ConversionHelper implements IProcessor, IUsesPlaceholder {
	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

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
					$this->placeholderManager->getPlaceholder( "<!--$macroNode->textContent-->" ),
					__METHOD__
				),
				$macroNode
			);
		}
	}
}
