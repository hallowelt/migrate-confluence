<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class PanelMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'panel';
	}
}
