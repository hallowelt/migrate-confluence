<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class DrawioSketchMacro extends DrawioMacro {

	public const MACRO_NAME = 'drawio-sketch';

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'DrawioSketch';
	}
}
