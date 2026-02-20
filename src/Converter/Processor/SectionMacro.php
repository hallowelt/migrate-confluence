<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class SectionMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'section';
	}
}
