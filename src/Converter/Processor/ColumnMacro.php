<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class ColumnMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'column';
	}
}
