<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroColumn extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'column';
	}
}
