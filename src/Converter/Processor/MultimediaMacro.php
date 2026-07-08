<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * <ac:structured-macro ac:name="multimedia">
 *	 <ac:parameter ac:name="name">
 *	   <ri:attachment ri:filename="Dummy.doc"/>
 *	 </ac:parameter>
 * </ac:structured-macro>
 */
class MultimediaMacro extends ViewFileMacro {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'multimedia';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'Multimedia';
	}

}
