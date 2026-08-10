<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class TmMacro extends SymbolMacroBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'tm';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSymbol(): string {
		return "\u{2122}";
	}
}
