<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Office Connector macro; same parameters and output as `view-file`.
 *
 * This is a similar macro as view-file with a bit different feature set, that
 * we migrate nonetheless into the same MW structure.
 *
 * <ac:structured-macro ac:name="viewfile">
 *   <ac:parameter ac:name="name">
 *      <ri:attachment ri:filename="Dummy.docx"/>
 *   </ac:parameter>
 * </ac:structured-macro>
 */
class OfficeViewfileMacro extends ViewFileMacro {

	public const MACRO_NAME = 'viewfile';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;
}
