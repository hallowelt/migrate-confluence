<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class BgcolorMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'bgcolor';

	public const TAG = 'div';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function mapParams( array $params ): array {
		// The color may also arrive as the unnamed (legacy) parameter
		$color = $params['color'] ?? $params[''] ?? 'green';
		unset( $params['color'], $params[''] );
		return $this->addStyle( parent::mapParams( $params ), "background-color: $color;" );
	}
}
