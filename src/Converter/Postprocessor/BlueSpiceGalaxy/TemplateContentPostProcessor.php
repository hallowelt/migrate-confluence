<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor\BlueSpiceGalaxy;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\TemplateContentPostProcessor as BaseProcessor;

class TemplateContentPostProcessor extends BaseProcessor {

	/**
	 * Uses a category instead of an SMW property. The condition keeps the category from being set
	 * when the template is transcluded, where the preload parameter `$1` is still unreplaced.
	 *
	 * @inheritDoc
	 */
	protected function getCreatedByMacroMarker(): string {
		return '{{#ifeq:{{#pos:$1|$}}||[[Category:Created by macro $1]]}}';
	}
}
