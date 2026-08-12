<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikisConfigSpacesForWikiName
 */
class WikisConfigSpacesForWikiNameTest extends TestCase {

	public function testReturnsEmptyArrayWhenNoRowsExist(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$this->assertSame( [], $db->getWikisConfigSpacesForWikiName( 'shared-wiki' ) );
	}

	public function testReturnsAllSpacesForWikiName(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addSpace( 42, 'ABC', 'Some space', 'ABC', '', '', -1, -1 );
		$db->addSpace( 23, 'DEVOPS', 'DevOps', 'DEVOPS', '', '', -1, -1 );
		$db->addSpace( 52, 'INF', 'Infra', 'INF', '', '', -1, -1 );

		$db->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$db->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );
		$db->addWikisConfig( 'INF', 'foreign-wiki', 'INF', '' );

		$spaces = $db->getWikisConfigSpacesForWikiName( 'shared-wiki' );

		$this->assertCount( 2, $spaces );

		$spaceKeys = array_column( $spaces, 'space_key' );
		$this->assertEqualsCanonicalizing( [ 'ABC', 'DEVOPS' ], $spaceKeys );

		$spaceNames = [];
		foreach ( $spaces as $space ) {
			$spaceNames[(string)$space['space_key']] = (string)$space['space_name'];
		}

		$this->assertSame( 'Some space', $spaceNames['ABC'] );
		$this->assertSame( 'DevOps', $spaceNames['DEVOPS'] );
	}
}
