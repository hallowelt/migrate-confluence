<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

class RecentlyUpdatedMacro extends StructuredMacroProcessorBase {

	/**
	 * @param string $wikiTitle
	 */
	public function __construct( private string $wikiTitle ) {
	}

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
	protected function doProcessMacro( DOMNode $node ): void {
		$namespace = '';
		$titleParts = explode( ':', $this->wikiTitle, 2 );
		if ( count( $titleParts ) === 2 ) {
			$namespace = $titleParts[0];
		}

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode(
				"{{RecentlyUpdated|namespace=$namespace}}",
			),
			$node
		);
	}
}
