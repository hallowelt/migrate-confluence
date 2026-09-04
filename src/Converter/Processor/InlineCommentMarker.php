<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

/**
 *
 */
class InlineCommentMarker extends ConversionHelper implements IProcessor {

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 */
	public function __construct(
		private IConverterDataWriter $writer,
		private int $currentSpaceId
	) {}

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

			$this->writer->registerDefaultPage(
				$this->currentSpaceId,
				'InlineComment'
			);
		}
	}
}
