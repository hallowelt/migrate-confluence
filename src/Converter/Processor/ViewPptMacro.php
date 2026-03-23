<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="viewppt">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.pdf"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewPptMacro extends ViewFileMacro {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'viewppt';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewPpt';
	}

}
