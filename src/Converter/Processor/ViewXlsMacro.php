<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="viewxls">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.xls"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewXlsMacro extends ViewFileMacro {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'viewxls';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewXls';
	}

}
