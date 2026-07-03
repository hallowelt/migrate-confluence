<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

/**
 * if we mangled the page name, print a DISPLAYTITLE to show the original title
 */
class AddDisplayTitle implements IPostprocessor {

	/**
	 * @param string $confluencePageTitle
	 * @param string $wikiTitle
	 */
	public function __construct( private string $confluencePageTitle, private string $wikiTitle ) {
	}

	/**
	 * @param string $wikiText
	 * @return string
	 */
	public function postprocess( string $wikiText ): string {
		/* strip, in this order, namespace and parent pages */
		$baseWikiTitle = basename( preg_replace( '/[^:]*:/', '', $this->wikiTitle ) );

		/* replace the minimal set of characters interfering with the wikisyntax
		 * below. Keep "foo/bar" structures intact, since that is allowed in
		 * Confluence and we want to compare against a most authentic original
		 * title. That's also why we cannot use TitleBuilder to sanitize it. */
		$baseConfluenceTitle = str_replace( [ '}', '|', '\\', ], '', $this->confluencePageTitle );

		if ( $baseWikiTitle !== str_replace( ' ', '_', $baseConfluenceTitle ) ) {
			/* append and set the magic word with noreplace, so that a
			 * potentially more specific title from an earlier processor can
			 * override it */
			$wikiText .= "\n{{DISPLAYTITLE:" . htmlspecialchars( $baseConfluenceTitle ) . "|noreplace}}";
		}
		return $wikiText;
	}
}
