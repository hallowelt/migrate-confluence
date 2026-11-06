<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * Restores the "valign" attribute of table cells and rows.
 *
 * Pandoc 3.11+ prefixes unknown HTML attributes with "data-", but the
 * migrated wiki pages rely on the plain "valign" attribute.
 */
class FixTableCellValign implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		return preg_replace( '/(?<=\s)data-valign=(?="(?:top|middle|bottom|baseline)")/', 'valign=', $wikiText );
	}
}
