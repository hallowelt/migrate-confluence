<?php

namespace HalloWelt\MigrateConfluence\Utility\Hooks;

/**
 * Hooks of the compose step.
 */
class ComposerHooks extends Hooks {

	/**
	 * Allow modification of the wikitext of a category page in categories.xml.
	 *
	 * @since 8.0.0
	 * @param string $content wikitext of the category page
	 * @param array $context context information: category (name without namespace prefix),
	 *   title (full wiki title), labels (Confluence labels that resulted in this category)
	 */
	public static function composeCategoryContent( string $content, array $context ): string {
		return self::filter( __FUNCTION__, $content, $context );
	}
}
