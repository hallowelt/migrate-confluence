<?php

namespace HalloWelt\MigrateConfluence\Utility;

class WikiTitleUniquifier {

	/**
	 * Use this method to uncolide wiki titles.
	 * This method returns eighter the wiki title or the wiki title appended with a number
	 * like My_Title-(1)
	 *
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
