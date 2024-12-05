<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="details" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="id">control</ac:parameter>
 *     <ac:rich-text-body>
 *       <h3>Control details</h3>
 *       <table class="wrapped">
 */
class DetailsMacro extends ConvertMacroToTemplateBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'details';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Details';
	}
}
