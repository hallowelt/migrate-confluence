<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroExcerptInclude extends StructuredMacroInclude {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'excerpt-include';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->getMacroParams( $node );
		// TBD
	}
}
