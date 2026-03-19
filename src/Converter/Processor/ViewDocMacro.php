<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="viewdoc">
 *	<ac:parameter ac:name="name">
 *		<ri:attachment ri:filename="Dummy.doc"/>
 *	</ac:parameter>
 * </ac:structured-macro>
 */
class ViewDocMacro extends ViewFileMacro {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'viewdoc';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewDoc';
	}

}
