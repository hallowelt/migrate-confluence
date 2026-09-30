<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="multimedia">
 *	 <ac:parameter ac:name="name">
 *	   <ri:attachment ri:filename="Dummy.doc"/>
 *	 </ac:parameter>
 * </ac:structured-macro>
 */
class MultimediaMacro extends ViewFileMacro {

	public const MACRO_NAME = 'multimedia';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'ParserFunctions' ];

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Multimedia';
	}

}
