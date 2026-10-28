<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Embeds the URL through the "Iframe" widget, which only accepts allowed URL protocols.
 */
class IframeMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'iframe';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'Widgets' ];

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Iframe';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWidgetNames(): array {
		return [ 'Iframe' ];
	}
}
