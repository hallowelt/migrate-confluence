<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getMapSpaceIdToPrefix
 */
class SpaceIdToPrefixMapTest extends TestCase {

	public function testUsesWikisConfigNamespaceAndRootPage(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addSpace( 42, 'ABC', 'Some space', 'OLD', '', 'OldRoot', -1, -1 );
		$db->addWikisConfig( 'ABC', 'shared-wiki', 'NEW', 'NewRoot' );

		$this->assertSame( 'NEW:NewRoot/', $db->getMapSpaceIdToPrefix()[42] );
	}

	public function testAllowsMainNamespaceFromWikisConfig(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addSpace( 42, 'ABC', 'Some space', 'OLD', '', 'OldRoot', -1, -1 );
		$db->addWikisConfig( 'ABC', 'shared-wiki', '', '' );

		$this->assertSame( '', $db->getMapSpaceIdToPrefix()[42] );
	}

	public function testFallsBackToSpacePrefixWhenNoWikisConfigExists(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();

		$db->addSpace( 42, 'ABC', 'Some space', 'ABC', '', 'Root', -1, -1 );

		$this->assertSame( 'ABC:Root/', $db->getMapSpaceIdToPrefix()[42] );
	}
}
