<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="warning" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
 *	<ac:parameter ac:name="title">/api/Device/devices</ac:parameter>
 *	<ac:rich-text-body>
 *		<p class="title">...</p>
 *		<p>...</p>
 *	</ac:rich-text-body>
 * </ac:structured-macro>
 */
class TipMacro extends ConvertMacroToTemplateBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'tip';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Tip';
	}
}
