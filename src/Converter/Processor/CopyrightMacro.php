<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="copyright" ac:schema-version="1" ac:macro-id="12345">
 *   <ac:rich-text-body>Product Name</ac:rich-text-body>
 * </ac:structured-macro>
 */
class CopyrightMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'copyright';

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Copyright';
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
