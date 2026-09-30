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
class ExpandMacro extends ConvertMacroToTemplateWithBodyBase {

	public const MACRO_NAME = 'expand';

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'ExpandStart';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'ExpandEnd';
	}
}
