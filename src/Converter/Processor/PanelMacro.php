<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class PanelMacro extends ConvertMacroToTemplateBase {

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
	protected function getWikiTextTemplateName(): string {
		return 'Panel';
	}
}
