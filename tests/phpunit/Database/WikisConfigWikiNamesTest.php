<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikisConfigWikiNames
 */
class WikisConfigWikiNamesTest extends TestCase {

	public function testReturnsEmptyArrayWhenNoRowsExist(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$this->assertSame( [], $db->getWikisConfigWikiNames() );
	}

	public function testReturnsAllWikiNames(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$db->addWikisConfig( 'DEVOPS', 'foreign-wiki', 'DEVOPS', '' );

		$this->assertEqualsCanonicalizing(
			[ 'shared-wiki', 'foreign-wiki' ],
			$db->getWikisConfigWikiNames()
		);
	}
}
