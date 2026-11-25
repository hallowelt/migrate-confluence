<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class CenterMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'center';

	public const TAG = 'div';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function mapParams( array $params ): array {
		return $this->addStyle( parent::mapParams( $params ), 'text-align: center;' );
	}
}
