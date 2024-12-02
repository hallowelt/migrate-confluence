<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroAttachments extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'attachments';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		// TBD
	}
}
