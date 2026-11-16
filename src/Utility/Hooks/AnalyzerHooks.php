<?php

namespace HalloWelt\MigrateConfluence\Utility\Hooks;

use SplFileInfo;

/**
 * Hooks of the analyze step.
 */
class AnalyzerHooks extends Hooks {

	/**
	 * Control whether the current entities.xml file should be considered.
	 *
	 * @since 8.0.0
	 * @param bool $include whether to include the file. Defaults to true.
	 * @param SplFileInfo $file the import XML file currently processed
	 */
	public static function includeSourceFile( bool $include, SplFileInfo $file ): bool {
		return self::filter( __FUNCTION__, $include, $file );
	}
}
