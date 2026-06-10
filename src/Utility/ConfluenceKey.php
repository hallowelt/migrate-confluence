<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ConfluenceKey {

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @return string
	 */
	public static function newPageKey( string $spaceKey, string $confluenceTitle ): string {
		return str_replace( ' ', '_', "Confluence---$spaceKey---$confluenceTitle" );
	}

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	public static function newFileKey( string $spaceKey, string $confluenceTitle, string $origFilename ): string {
		return str_replace( ' ', '_', "Confluence---$spaceKey---$confluenceTitle---$origFilename" );
	}
}
