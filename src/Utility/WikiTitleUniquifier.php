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

	/**
	 * Uncollides wiki titles per destination wiki, so items headed for different output
	 * wikis (via a `--wikis` mapping) don't fight over the same title, e.g. two spaces'
	 * home pages both named "Main Page".
	 *
	 * @param array<int,string> $wikiTitles itemId => title
	 * @param array<int,int> $itemIdToSpaceId itemId => space_id
	 * @param array<int,int[]> $spaceIdToWikiGroup space_id => space_ids sharing its destination wiki
	 * @param callable $getExistingTitles fn(int[] $spaceIds): string[]
	 * @return array<int,string>
	 */
	public static function makeUniquePerWiki(
		array $wikiTitles,
		array $itemIdToSpaceId,
		array $spaceIdToWikiGroup,
		callable $getExistingTitles
	): array {
		$groups = [];
		foreach ( $wikiTitles as $itemId => $wikiTitle ) {
			$spaceId = $itemIdToSpaceId[$itemId] ?? null;
			$groupSpaceIds = $spaceId !== null
				? ( $spaceIdToWikiGroup[$spaceId] ?? [ $spaceId ] )
				: [];
			sort( $groupSpaceIds );
			$groupKey = implode( ',', $groupSpaceIds );

			$groups[$groupKey]['spaceIds'] = $groupSpaceIds;
			$groups[$groupKey]['titles'][$itemId] = $wikiTitle;
		}

		$result = [];
		foreach ( $groups as $group ) {
			$existingTitles = $getExistingTitles( $group['spaceIds'] );
			$result += self::makeUnique( $group['titles'], $existingTitles );
		}

		return $result;
	}
}
