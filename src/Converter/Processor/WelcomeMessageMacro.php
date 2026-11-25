<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="welcome-message"/>
 */
class WelcomeMessageMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'welcome-message';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'WelcomeMessage';
	}

	/**
	 * @return bool
	 */
	protected function addLinebreakInsideTemplate(): bool {
		return false;
	}

	/**
	 * @return bool
	 */
	protected function addLinebreakAfterTemplate(): bool {
		return false;
	}
}
