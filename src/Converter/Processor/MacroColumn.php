<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class MacroColumn extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'column';
	}
}
