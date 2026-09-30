<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="viewpdf">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.pdf"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewPdfMacro extends ViewFileMacro {

	public const MACRO_NAME = 'viewpdf';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewPdf';
	}

}
