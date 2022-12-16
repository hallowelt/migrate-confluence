<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroDrawIO extends StructuredMacroProcessorBase {

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
		return 'draw-io';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
	}
}
