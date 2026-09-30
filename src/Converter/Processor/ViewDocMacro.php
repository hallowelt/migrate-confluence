<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="viewdoc">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.doc"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewDocMacro extends ViewFileMacro {

	public const MACRO_NAME = 'viewdoc';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewDoc';
	}

}
