<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class MacroPanel extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'panel';
	}
}
