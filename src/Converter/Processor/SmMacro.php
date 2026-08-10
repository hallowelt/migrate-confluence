<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class SmMacro extends SymbolMacroBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'sm';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSymbol(): string {
		return "\u{2120}";
	}
}
