<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class DrawioSketchMacro extends DrawioMacro {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'drawio-sketch';
	}

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'DrawioSketch';
	}
}
