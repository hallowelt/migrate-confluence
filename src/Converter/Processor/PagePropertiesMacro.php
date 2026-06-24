<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Alias for DetailsMacro covering the renamed "Page Properties" macro
 * (ac:name="page-properties"), introduced in newer Confluence versions.
 */
class PagePropertiesMacro extends DetailsMacro {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'page-properties';
	}
}
