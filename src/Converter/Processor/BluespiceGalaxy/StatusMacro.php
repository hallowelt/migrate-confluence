<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BluespiceGalaxy;

use HalloWelt\MigrateConfluence\Converter\Processor\ConvertMacroToTemplateBase;

/**
 * Convert into <status>
 *
 * @todo implement the thing
 */
class StatusMacro extends ConvertMacroToTemplateBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'status';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Status';
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
