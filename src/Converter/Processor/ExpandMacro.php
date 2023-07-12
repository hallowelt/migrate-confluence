<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="expand">
 * 	<ac:parameter ac:name="title">click here to expand</ac:parameter>
 *     <ac:rich-text-body>
 *          <ul>
 *              <li>something
 *                  <ul>
 *                      <li>something more</li>
 *                  </ul>
 *              </li>
 *          </ul>
 *      </ac:rich-text-body>
 *  </ac:structured-macro>
 */
class ExpandMacro extends ConvertMacroToTemplateBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'expand';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Expand';
	}
}
