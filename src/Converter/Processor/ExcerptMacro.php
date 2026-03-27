<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * @see https://confluence.atlassian.com/doc/excerpt-macro-148062.html
 * @see https://docs.atlassian.com/DAC/javadoc/confluence/4.0/reference/com/atlassian/confluence/macro/Macro.OutputType.html
 */
class ExcerptMacro extends ConvertMacroToTemplateWithBodyBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'excerpt';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'ExcerptStart';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'ExcerptEnd';
	}
}
