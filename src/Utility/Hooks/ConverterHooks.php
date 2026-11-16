<?php

namespace HalloWelt\MigrateConfluence\Utility\Hooks;

use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

/**
 * Hooks of the convert step.
 */
class ConverterHooks extends Hooks {

	/**
	 * Allow modification of the raw input before processing it.
	 *
	 * @since 8.0.0
	 * @param string $content content before any processing
	 * @param array $context context information: pageId, wikiPageTitle, confluencePageTitle, currentSpace, contentType
	 * @param DBConversionDataLookup $dataLookup data lookup object
	 */
	public static function convertContentPre(
		string $content, array $context, DBConversionDataLookup $dataLookup
	): string {
		return self::filter( __FUNCTION__, $content, $context, $dataLookup );
	}

	/**
	 * Allow modification of the output after processing.
	 *
	 * @since 8.0.0
	 * @param string $content content after all processing
	 * @param array $context context information: pageId, wikiPageTitle, confluencePageTitle, currentSpace, contentType
	 * @param DBConversionDataLookup $dataLookup data lookup object
	 */
	public static function convertContentPost(
		string $content, array $context, DBConversionDataLookup $dataLookup
	): string {
		return self::filter( __FUNCTION__, $content, $context, $dataLookup );
	}
}
