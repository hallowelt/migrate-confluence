<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * <ac:structured-macro ac:name="details" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="id">control</ac:parameter>
 *     <ac:rich-text-body>
 *       <h3>Control details</h3>
 *       <table class="wrapped">
 *     </ac:rich-text-body>
 *     <ac:rich-text-body>
 * 	     <h3>There may be multiple rich texts</h3>
 *     </ac:rich-text-body>
 *     ...
 * </ac:structured-macro>
 */
class DetailsMacro  extends ConvertMacroToTemplateBase {

	/**
	 * @return string
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
