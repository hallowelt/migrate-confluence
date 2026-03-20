<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class LayoutCell extends Layout {

	/**
	 * @return string
	 */
	protected function getTagName(): string {
		return 'layout-cell';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'LayoutCell';
	}
}
