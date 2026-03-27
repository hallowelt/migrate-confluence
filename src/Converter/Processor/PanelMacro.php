<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class PanelMacro extends ConvertMacroToTemplateWithBodyBase {

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'panel';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'PanelStart';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'PanelEnd';
	}
}
