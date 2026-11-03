<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * Deprecated macro; the `link` parameter is kept as a raw href, it is not resolved to a wiki page.
 */
class ClickableMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'clickable';

	public const TAG = 'a';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @inheritDoc
	 */
	protected function mapParams( array $params ): array {
		if ( isset( $params['link'] ) ) {
			$params['href'] = $params['link'];
			unset( $params['link'] );
		}
		return parent::mapParams( $params );
	}
}
