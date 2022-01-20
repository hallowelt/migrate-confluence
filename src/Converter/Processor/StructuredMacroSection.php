<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroSection extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'section';
	}
}
