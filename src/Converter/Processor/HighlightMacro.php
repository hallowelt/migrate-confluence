<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class HighlightMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'highlight';

	public const TAG = 'span';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function mapParams( array $params ): array {
		$color = $params['color'] ?? $params[''] ?? 'yellow';
		unset( $params['color'], $params[''] );
		return $this->addStyle( parent::mapParams( $params ), "background-color: $color;" );
	}
}
