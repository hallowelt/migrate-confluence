<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class CopyrightMacro extends SymbolMacroBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'copyright';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSymbol(): string {
		return "\u{00A9}";
	}
}
