<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class DrawioSketchMacro extends DrawioMacro {

	public const MACRO_NAME = 'drawio-sketch';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'DrawioEditor', 'ParserFunctions' ];

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'DrawioSketch';
	}
}
