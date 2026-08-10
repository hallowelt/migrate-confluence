<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class RegTmMacro extends SymbolMacroBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'reg-tm';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSymbol(): string {
		return "\u{00AE}";
	}
}
