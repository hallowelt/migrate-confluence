<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class RecentlyUpdatedMacro extends StructuredMacroProcessorBase {

	/**
	 * @var string
	 */
	private $currentPageTitle = '';

	/**
	 * @param string $currentPageTitle
	 */
	public function __construct( string $currentPageTitle ) {
		$this->currentPageTitle = $currentPageTitle;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'recently-updated';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$namespace = '';
		$titleParts = explode( ':', $this->currentPageTitle, 2 );
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
