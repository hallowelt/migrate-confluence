<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class LayoutSection extends Layout {

	/**
	 * @return string
	 */
	protected function getTagName(): string {
		return 'layout-section';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateStartName(): string {
		return 'LayoutSectionStart';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'LayoutSectionEnd';
	}
}
