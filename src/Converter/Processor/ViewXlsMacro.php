<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="viewxls">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.xls"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewXlsMacro extends ViewFileMacro {

	public const MACRO_NAME = 'viewxls';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewXls';
	}

}
