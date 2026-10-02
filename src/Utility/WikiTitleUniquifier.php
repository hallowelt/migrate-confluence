<?php

namespace HalloWelt\MigrateConfluence\Utility;

class WikiTitleUniquifier {

	/**
	 * @param array<int,string> $wikiTitles
	 * @return array<int,string>
	 */
	public static function makeUnique( array $wikiTitles, array $existingWikiTitles ): array {
		$usedWikiTitles = array_fill_keys( $existingWikiTitles, true );

		foreach ( $wikiTitles as $itemId => $wikiTitle ) {
			$baseTitle = $wikiTitle;
			$counter = 1;
			while ( isset( $usedWikiTitles[$wikiTitle] ) ) {
				$wikiTitle = $baseTitle . '-(' . $counter . ')';
				$counter++;
			}

			$usedWikiTitles[$wikiTitle] = true;
			$wikiTitles[$itemId] = $wikiTitle;
		}

		return $wikiTitles;
	}
}
