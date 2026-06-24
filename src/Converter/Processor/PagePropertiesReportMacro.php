<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Alias for DetailsSummaryMacro covering the renamed "Page Properties Report"
 * macro (ac:name="page-properties-report"), introduced in newer Confluence versions.
 */
class PagePropertiesReportMacro extends DetailsSummaryMacro {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'page-properties-report';
	}
}
