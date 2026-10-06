<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

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
class DetailsMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'details';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'ParserFunctions' ];

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'DetailsStart';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'DetailsEnd';
	}
}
