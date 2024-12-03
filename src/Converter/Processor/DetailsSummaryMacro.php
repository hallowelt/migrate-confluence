<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="detailssummary" ac:schema-version="3" ac:macro-id="...">
 *   <ac:parameter ac:name="firstcolumn">...</ac:parameter>
 *   <ac:parameter ac:name="headings">...</ac:parameter>
 *   <ac:parameter ac:name="sortBy">Title</ac:parameter>
 *   <ac:parameter ac:name="cql">label = "..." and parent = currentContent ( )</ac:parameter>
 * </ac:structured-macro>
 */
class DetailsSummaryMacro extends ConvertMacroToTemplateBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'detailssummary';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'DetailsSummary';
	}
}
