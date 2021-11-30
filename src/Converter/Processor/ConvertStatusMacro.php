<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="status" ac:schema-version="1" ac:macro-id="1b880702-ef9e-4f6c-be5d-717c6e4cdaae">
 *   <ac:parameter ac:name="title">Good Status</ac:parameter>
 *   <ac:parameter ac:name="colour">Red</ac:parameter>
 * </ac:structured-macro>
 */
class ConvertStatusMacro extends ConvertMacroToTemplateBase {

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
}
