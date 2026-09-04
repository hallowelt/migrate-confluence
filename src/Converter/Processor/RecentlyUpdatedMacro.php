<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;

class RecentlyUpdatedMacro extends StructuredMacroProcessorBase {

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 * @param string $wikiTitle
	 */
	public function __construct(
		private IConverterDataWriter $writer,
		private int $currentSpaceId,
		private string $wikiTitle
	) {}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'recently-updated';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$namespace = '';
		$titleParts = explode( ':', $this->wikiTitle, 2 );
		if ( count( $titleParts ) === 2 ) {
			$namespace = $titleParts[0];
		}

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				"{{RecentlyUpdated|namespace=$namespace}}",
				__METHOD__
			),
			$node
		);

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			'RecentlyUpdated'
		);
	}
}
